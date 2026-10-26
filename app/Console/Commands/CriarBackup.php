<?php

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Database\Connection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use PharData;
use RuntimeException;
use Symfony\Component\Process\Process;
use Throwable;

#[Signature('app:backup
    {--sem-base-dados : Não inclui a base de dados}
    {--sem-ficheiros : Não inclui os ficheiros}
    {--destino= : Pasta de destino do backup}
    {--manter-dias= : Dias de retenção (0 desativa)}')]
#[Description('Cria um backup comprimido da base de dados e dos ficheiros')]
class CriarBackup extends Command
{
    public function handle(): int
    {
        $incluirBaseDados = ! $this->option('sem-base-dados');
        $incluirFicheiros = ! $this->option('sem-ficheiros');

        if (! $incluirBaseDados && ! $incluirFicheiros) {
            $this->error('Indique pelo menos a base de dados ou os ficheiros.');

            return self::FAILURE;
        }

        $destino = (string) ($this->option('destino') ?: config('backup.path'));
        $retencao = $this->obterRetencao();

        if ($destino === '' || $retencao === null) {
            $this->error('Configuração de backup inválida.');

            return self::FAILURE;
        }

        $tarPath = null;
        $dumpPath = null;
        $caminhoFinal = null;

        try {
            File::ensureDirectoryExists($destino, 0700);
            @chmod($destino, 0700);

            if (! File::isDirectory($destino)) {
                throw new RuntimeException(
                    'Não foi possível criar a pasta de destino: '.$destino
                );
            }

            $tarPath = tempnam(sys_get_temp_dir(), 'sge-backup-');

            if ($tarPath === false) {
                throw new RuntimeException(
                    'Não foi possível criar o ficheiro temporário do backup.'
                );
            }

            @unlink($tarPath);
            $tarPath .= '.tar';
            @unlink($tarPath);

            $tar = new PharData($tarPath);
            @chmod($tarPath, 0600);
            $totalFicheiros = 0;
            $driver = null;

            if ($incluirBaseDados) {
                $dump = $this->criarDumpBaseDados();
                $dumpPath = $dump['caminho'];
                $driver = $dump['driver'];

                $tar->addFile($dumpPath, $dump['nome']);
            }

            if ($incluirFicheiros) {
                $totalFicheiros += $this->adicionarDiretorio(
                    $tar,
                    (string) config('backup.files.private'),
                    'ficheiros/private',
                    $destino
                );

                $totalFicheiros += $this->adicionarDiretorio(
                    $tar,
                    (string) config('backup.files.public'),
                    'ficheiros/public',
                    $destino
                );
            }

            $tar->addFromString(
                'manifest.json',
                $this->manifesto(
                    $incluirBaseDados,
                    $incluirFicheiros,
                    $driver,
                    $totalFicheiros
                )
            );

            unset($tar);

            $caminhoFinal = $this->nomeDoBackup($destino);

            $this->comprimir($tarPath, $caminhoFinal);
            @chmod($caminhoFinal, 0600);

            try {
                $this->limparAntigos($destino, $retencao, $caminhoFinal);
            } catch (Throwable $e) {
                $this->warn(
                    'Backup criado, mas a retenção falhou: '.$e->getMessage()
                );
            }

            try {
                Log::info('Backup criado.', [
                    'caminho' => $caminhoFinal,
                    'base_dados' => $driver,
                    'ficheiros' => $totalFicheiros,
                ]);
            } catch (Throwable) {
                // O backup já está criado; o log não o deve invalidar.
            }

            $this->info('Backup criado: '.$caminhoFinal);
            $this->line(
                'Base de dados: '
                .($driver ?? 'não incluída')
            );
            $this->line(
                'Ficheiros: '
                .($incluirFicheiros ? $totalFicheiros : 'não incluídos')
            );
            $this->line(
                'Retenção: '
                .($retencao > 0 ? $retencao.' dias' : 'desativada')
            );

            return self::SUCCESS;
        } catch (Throwable $e) {
            unset($tar);

            if ($caminhoFinal && File::exists($caminhoFinal)) {
                File::delete($caminhoFinal);
            }

            try {
                Log::error('Falha ao criar backup.', [
                    'excecao' => $e,
                    'destino' => $destino,
                ]);
            } catch (Throwable) {
                // A falha original deve ser reportada mesmo sem log.
            }

            $this->error('Falha ao criar backup: '.$e->getMessage());

            return self::FAILURE;
        } finally {
            if ($tarPath && File::exists($tarPath)) {
                File::delete($tarPath);
            }

            if ($dumpPath && File::exists($dumpPath)) {
                File::delete($dumpPath);
            }
        }
    }

    /**
     * Lê a retenção da opção ou da configuração.
     */
    private function obterRetencao(): ?int
    {
        $opcao = $this->option('manter-dias');

        if ($opcao === null || $opcao === '') {
            return max(0, (int) config('backup.keep_days', 14));
        }

        if (! preg_match('/^\d+$/', (string) $opcao)) {
            $this->error('O valor de --manter-dias deve ser um número inteiro.');

            return null;
        }

        return (int) $opcao;
    }

    /**
     * Cria o dump da base de dados num ficheiro temporário.
     *
     * @return array{caminho: string, nome: string, driver: string}
     */
    private function criarDumpBaseDados(): array
    {
        $connection = DB::connection();
        $caminho = tempnam(sys_get_temp_dir(), 'sge-db-');

        if ($caminho === false) {
            throw new RuntimeException(
                'Não foi possível criar o ficheiro temporário da base de dados.'
            );
        }

        try {
            $driver = $connection->getDriverName();

            if ($driver === 'mysql') {
                $this->dumpMysql($connection, $caminho);

                return [
                    'caminho' => $caminho,
                    'nome' => 'base-dados.sql',
                    'driver' => $driver,
                ];
            }

            if ($driver === 'sqlite') {
                $this->dumpSqlite($connection, $caminho);

                return [
                    'caminho' => $caminho,
                    'nome' => 'base-dados.sqlite',
                    'driver' => $driver,
                ];
            }

            throw new RuntimeException(
                'Driver de base de dados não suportado: '.$driver
            );
        } catch (Throwable $e) {
            @unlink($caminho);

            throw $e;
        }
    }

    /**
     * Exporta a base de dados MySQL com mysqldump.
     */
    private function dumpMysql(Connection $connection, string $destino): void
    {
        $config = $connection->getConfig();

        $comando = [
            (string) config('backup.mysqldump_binary', 'mysqldump'),
            '--default-character-set=utf8mb4',
            '--single-transaction',
            '--quick',
            '--no-tablespaces',
            '--set-gtid-purged=OFF',
        ];

        if (! empty($config['unix_socket'])) {
            $comando[] = '--socket='.$config['unix_socket'];
        } else {
            $comando[] = '--host='.($config['host'] ?? '127.0.0.1');
            $comando[] = '--port='.(string) ($config['port'] ?? 3306);
        }

        $comando[] = '--user='.(string) ($config['username'] ?? '');
        $comando[] = '--result-file='.$destino;
        $comando[] = (string) ($config['database'] ?? '');

        $process = new Process(
            $comando,
            base_path(),
            $this->ambienteMysql((string) ($config['password'] ?? '')),
            null,
            3600
        );

        $process->run();

        if (! $process->isSuccessful()) {
            $erro = trim($process->getErrorOutput());

            throw new RuntimeException(
                'mysqldump falhou'
                .($erro !== '' ? ': '.$erro : '.')
            );
        }
    }

    /**
     * Exporta a base de dados SQLite para um ficheiro consistente.
     */
    private function dumpSqlite(Connection $connection, string $destino): void
    {
        $baseDados = $connection->getDatabaseName();

        if ($baseDados === ':memory:' || ! File::exists($baseDados)) {
            throw new RuntimeException(
                'Não é possível copiar uma base SQLite em memória ou inexistente.'
            );
        }

        @unlink($destino);

        $pdo = $connection->getPdo();
        $pdo->exec('VACUUM INTO '.$pdo->quote($destino));
    }

    /**
     * Adiciona os ficheiros de uma pasta ao arquivo tar.
     */
    private function adicionarDiretorio(
        PharData $tar,
        string $origem,
        string $prefixo,
        string $destino
    ): int {
        if ($origem === '' || ! File::isDirectory($origem)) {
            return 0;
        }

        $destinoNormalizado = rtrim(
            str_replace('\\', '/', $destino),
            '/'
        ).'/';

        $total = 0;

        foreach (File::allFiles($origem) as $ficheiro) {
            $caminho = str_replace('\\', '/', $ficheiro->getPathname());

            if (str_starts_with($caminho, $destinoNormalizado)) {
                continue;
            }

            if (basename($caminho) === '.gitignore') {
                continue;
            }

            $relativo = str_replace(
                '\\',
                '/',
                $ficheiro->getRelativePathname()
            );

            if ($relativo === '') {
                continue;
            }

            $tar->addFile(
                $ficheiro->getPathname(),
                $prefixo.'/'.$relativo
            );

            $total++;
        }

        return $total;
    }

    /**
     * Cria o manifesto com informação sobre o backup.
     */
    private function manifesto(
        bool $incluirBaseDados,
        bool $incluirFicheiros,
        ?string $driver,
        int $totalFicheiros
    ): string {
        return json_encode(
            [
                'aplicacao' => (string) config('app.name'),
                'ambiente' => app()->environment(),
                'criado_em' => now()->toIso8601String(),
                'base_dados' => $driver,
                'inclui_base_dados' => $incluirBaseDados,
                'inclui_ficheiros' => $incluirFicheiros,
                'total_ficheiros' => $totalFicheiros,
            ],
            JSON_PRETTY_PRINT
            | JSON_UNESCAPED_UNICODE
            | JSON_UNESCAPED_SLASHES
            | JSON_THROW_ON_ERROR
        );
    }

    /**
     * Gera um nome único para o backup.
     */
    private function nomeDoBackup(string $destino): string
    {
        $destino = rtrim($destino, '\\/');
        $base = 'sge-backup-'.now()->format('Ymd-His');
        $caminho = $destino.DIRECTORY_SEPARATOR.$base.'.tar.gz';
        $contador = 1;

        while (File::exists($caminho)) {
            $caminho = $destino.DIRECTORY_SEPARATOR
                .$base.'-'.$contador.'.tar.gz';
            $contador++;
        }

        return $caminho;
    }

    /**
     * Comprime o tar para gzip em blocos, sem carregar tudo para memória.
     */
    private function comprimir(string $origem, string $destino): void
    {
        if (! extension_loaded('zlib')) {
            throw new RuntimeException(
                'A extensão zlib é necessária para comprimir o backup.'
            );
        }

        $entrada = fopen($origem, 'rb');

        if ($entrada === false) {
            throw new RuntimeException(
                'Não foi possível abrir o arquivo temporário do backup.'
            );
        }

        $saida = gzopen($destino, 'wb9');

        if ($saida === false) {
            fclose($entrada);

            throw new RuntimeException(
                'Não foi possível criar o arquivo comprimido do backup.'
            );
        }

        try {
            while (! feof($entrada)) {
                $bloco = fread($entrada, 1024 * 1024);

                if ($bloco === false) {
                    throw new RuntimeException(
                        'Não foi possível ler o arquivo temporário do backup.'
                    );
                }

                if ($bloco === '') {
                    continue;
                }

                if (gzwrite($saida, $bloco) === false) {
                    throw new RuntimeException(
                        'Não foi possível escrever o arquivo comprimido do backup.'
                    );
                }
            }
        } finally {
            fclose($entrada);
            gzclose($saida);
        }
    }

    /**
     * Remove backups mais antigos do que a retenção configurada.
     */
    private function limparAntigos(
        string $destino,
        int $dias,
        string $atual
    ): void {
        if ($dias <= 0) {
            return;
        }

        $destino = rtrim($destino, '\\/');
        $limite = now()->subDays($dias)->getTimestamp();

        foreach (File::glob(
            $destino.DIRECTORY_SEPARATOR.'sge-backup-*.tar.gz'
        ) as $ficheiro) {
            if ($ficheiro === $atual) {
                continue;
            }

            try {
                if (File::lastModified($ficheiro) < $limite) {
                    File::delete($ficheiro);
                }
            } catch (Throwable $e) {
                $this->warn(
                    'Não foi possível remover o backup antigo: '.$ficheiro
                );
            }
        }
    }

    /**
     * Mantém o ambiente atual e injeta a palavra-passe do MySQL.
     *
     * @return array<string, string>
     */
    private function ambienteMysql(string $password): array
    {
        $ambiente = getenv();

        if (! is_array($ambiente)) {
            $ambiente = [];
        }

        $ambiente['MYSQL_PWD'] = $password;

        return $ambiente;
    }
}
