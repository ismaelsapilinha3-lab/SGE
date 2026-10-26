<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

beforeEach(function () {
    $this->base = sys_get_temp_dir()
        .DIRECTORY_SEPARATOR.'sge-backup-test-'.uniqid();
    $this->private = $this->base.DIRECTORY_SEPARATOR.'private';
    $this->public = $this->base.DIRECTORY_SEPARATOR.'public';
    $this->destino = $this->base.DIRECTORY_SEPARATOR.'destino';

    File::ensureDirectoryExists($this->private);
    File::ensureDirectoryExists($this->public);

    File::put($this->private.DIRECTORY_SEPARATOR.'documento.txt', 'documento');
    File::put($this->public.DIRECTORY_SEPARATOR.'foto.txt', 'foto');

    config([
        'backup.files.private' => $this->private,
        'backup.files.public' => $this->public,
        'backup.path' => $this->destino,
    ]);
});

afterEach(function () {
    gc_collect_cycles();
    File::deleteDirectory($this->base);
});

it('cria um backup dos ficheiros e do manifesto', function () {
    $this->artisan('app:backup', [
        '--sem-base-dados' => true,
        '--destino' => $this->destino,
        '--manter-dias' => 0,
    ])->assertExitCode(0);

    $backups = File::glob(
        $this->destino.DIRECTORY_SEPARATOR.'sge-backup-*.tar.gz'
    );

    expect($backups)->toHaveCount(1);

    $archive = new PharData($backups[0]);

    expect($archive['ficheiros/private/documento.txt']->getContent())
        ->toBe('documento')
        ->and($archive['ficheiros/public/foto.txt']->getContent())
        ->toBe('foto');

    $manifest = json_decode(
        $archive['manifest.json']->getContent(),
        true
    );

    expect($manifest['inclui_base_dados'])->toBeFalse()
        ->and($manifest['inclui_ficheiros'])->toBeTrue()
        ->and($manifest['total_ficheiros'])->toBe(2);

    unset($archive);
});

it('cria um backup da base de dados MySQL', function () {
    if (DB::getDriverName() !== 'mysql') {
        $this->markTestSkipped('Este teste exige o driver MySQL.');
    }

    $this->artisan('app:backup', [
        '--sem-ficheiros' => true,
        '--destino' => $this->destino,
        '--manter-dias' => 0,
    ])->assertExitCode(0);

    $backups = File::glob(
        $this->destino.DIRECTORY_SEPARATOR.'sge-backup-*.tar.gz'
    );

    expect($backups)->toHaveCount(1);

    $archive = new PharData($backups[0]);

    expect($archive['base-dados.sql']->isFile())->toBeTrue();

    $sql = $archive['base-dados.sql']->getContent();

    expect($sql)->toContain('CREATE TABLE `utilizadores`');

    $manifest = json_decode(
        $archive['manifest.json']->getContent(),
        true
    );

    expect($manifest['base_dados'])->toBe('mysql')
        ->and($manifest['inclui_base_dados'])->toBeTrue();

    unset($archive);
});

it('cria um backup de uma base SQLite em ficheiro', function () {
    if (! extension_loaded('pdo_sqlite')) {
        $this->markTestSkipped('Este teste exige pdo_sqlite.');
    }

    $ligacaoOriginal = config('database.default');
    $baseDados = $this->base.DIRECTORY_SEPARATOR.'base.sqlite';

    File::put($baseDados, '');

    config([
        'database.connections.sqlite_backup' => [
            'driver' => 'sqlite',
            'database' => $baseDados,
            'prefix' => '',
            'foreign_key_constraints' => true,
        ],
        'database.default' => 'sqlite_backup',
    ]);

    DB::purge('sqlite_backup');

    try {
        DB::connection('sqlite_backup')->statement(
            'CREATE TABLE exemplo (
                id INTEGER PRIMARY KEY,
                nome TEXT NOT NULL
            )'
        );

        DB::connection('sqlite_backup')->table('exemplo')->insert([
            'nome' => 'teste',
        ]);

        $this->artisan('app:backup', [
            '--sem-ficheiros' => true,
            '--destino' => $this->destino,
            '--manter-dias' => 0,
        ])->assertExitCode(0);

        $backups = File::glob(
            $this->destino.DIRECTORY_SEPARATOR.'sge-backup-*.tar.gz'
        );

        expect($backups)->toHaveCount(1);

        $archive = new PharData($backups[0]);

        expect($archive['base-dados.sqlite']->isFile())->toBeTrue();

        $dump = $this->base.DIRECTORY_SEPARATOR.'dump.sqlite';
        File::put($dump, $archive['base-dados.sqlite']->getContent());

        unset($archive);

        $pdo = new PDO('sqlite:'.$dump);
        $total = $pdo->query('SELECT COUNT(*) FROM exemplo')->fetchColumn();

        expect((int) $total)->toBe(1);

        unset($pdo);
    } finally {
        config(['database.default' => $ligacaoOriginal]);
        DB::purge('sqlite_backup');
    }
});

it('remove backups mais antigos do que a retenção', function () {
    File::ensureDirectoryExists($this->destino);

    $antigo = $this->destino.DIRECTORY_SEPARATOR
        .'sge-backup-20200101-000000.tar.gz';
    $recente = $this->destino.DIRECTORY_SEPARATOR
        .'sge-backup-20260101-000000.tar.gz';

    File::put($antigo, 'antigo');
    File::put($recente, 'recente');

    touch($antigo, now()->subDays(10)->timestamp);
    touch($recente, now()->subDays(1)->timestamp);

    $this->artisan('app:backup', [
        '--sem-base-dados' => true,
        '--destino' => $this->destino,
        '--manter-dias' => 5,
    ])->assertExitCode(0);

    expect(File::exists($antigo))->toBeFalse()
        ->and(File::exists($recente))->toBeTrue();
});

it('recusa um backup sem base de dados e sem ficheiros', function () {
    $this->artisan('app:backup', [
        '--sem-base-dados' => true,
        '--sem-ficheiros' => true,
        '--destino' => $this->destino,
    ])->assertExitCode(1);

    expect(File::glob(
        $this->destino.DIRECTORY_SEPARATOR.'sge-backup-*.tar.gz'
    ))->toBeEmpty();
});
