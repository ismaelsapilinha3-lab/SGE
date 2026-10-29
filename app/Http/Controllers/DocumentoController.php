<?php

namespace App\Http\Controllers;

use App\Models\Candidatura;
use App\Models\Documento;
use App\Models\HistoricoEstado;
use App\Services\AcessoFicheiroService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Throwable;

class DocumentoController extends Controller
{
    public function __construct(
        private readonly AcessoFicheiroService $acessos
    ) {}

    /*
    |--------------------------------------------------------------------------
    | Visualizar documento
    |--------------------------------------------------------------------------
    */

    public function download(
        Request $request,
        Documento $documento
    ) {
        $user = $request->user();

        if (! $user || ! $user->ativo) {
            return response()->json([
                'message' => 'Utilizador não autorizado.',
            ], 403);
        }

        $candidatura = $documento->candidatura;

        if (! $candidatura) {
            return response()->json([
                'message' => 'Candidatura não encontrada.',
            ], 404);
        }

        /*
         * A política centraliza a regra: o dono, o coordenador da direção
         * e o administrador podem consultar o documento.
         */
        if (! Gate::forUser($user)->allows('view', $documento)) {
            return response()->json([
                'message' => 'Não tem autorização para consultar este documento.',
            ], 403);
        }

        $disk = Storage::disk('local');

        if (! $disk->exists($documento->caminho_arquivo)) {
            return response()->json([
                'message' => 'O ficheiro solicitado não foi encontrado.',
            ], 404);
        }

        $this->acessos->registarDocumento($request, $user, $documento);

        return response()->file(
            $disk->path($documento->caminho_arquivo),
            [
                'Content-Disposition' => 'inline; filename="'.
                    addslashes($documento->nome_arquivo).
                    '"',
            ]
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Substituir documento
    |--------------------------------------------------------------------------
    */

    public function substituir(
        Request $request,
        Documento $documento
    ) {
        $user = $request->user();

        if (
            ! $user ||
            ! Gate::forUser($user)->allows('substituir', $documento)
        ) {
            return response()->json([
                'message' => 'Apenas o estagiário pode substituir este documento.',
            ], 403);
        }

        $validated = $request->validate([
            'ficheiro' => [
                'required',
                'file',
                'max:5120',
                'mimes:pdf,jpg,jpeg,png',
            ],
        ]);

        $ficheiro = $validated['ficheiro'];

        $disk = Storage::disk('local');

        $caminhoNovo = null;

        try {
            $novoDocumento = DB::transaction(function () use (
                $user,
                $documento,
                $ficheiro,
                &$caminhoNovo
            ) {
                /*
                 * Bloquear a candidatura para serializar as
                 * substituições e verificar novamente o estado.
                 */
                $candidatura = Candidatura::where(
                    'id_candidatura',
                    $documento->id_candidatura
                )
                    ->lockForUpdate()
                    ->firstOrFail();

                if (! $candidatura->pertenceAo($user)) {
                    abort(403, 'Não tem autorização para alterar este documento.');
                }

                if ($candidatura->estado !== 'em_analise') {
                    abort(
                        422,
                        'A substituição só é permitida durante a análise da candidatura.'
                    );
                }

                /*
                 * Impedir a substituição de uma versão antiga.
                 * A versão mais recente é considerada a actual.
                 */
                $ultimaVersao = Documento::where(
                    'id_candidatura',
                    $candidatura->id_candidatura
                )
                    ->where(
                        'tipo_documento',
                        $documento->tipo_documento
                    )
                    ->max('versao');

                if ((int) $documento->versao !== (int) $ultimaVersao) {
                    abort(
                        409,
                        'Este documento já tem uma versão mais recente. Actualize a página.'
                    );
                }

                $versaoNova = ((int) $ultimaVersao) + 1;

                $pasta = 'documentos/'.
                    $candidatura->id_candidatura;

                /*
                 * Guardar o novo ficheiro no disco local.
                 * O ficheiro anterior não é apagado.
                 */
                $caminhoNovo = $ficheiro->store($pasta, 'local');

                if (! $caminhoNovo) {
                    throw new RuntimeException(
                        'Não foi possível guardar o novo documento.'
                    );
                }

                $novoDocumento = Documento::create([
                    'id_candidatura' => $candidatura->id_candidatura,

                    'tipo_documento' => $documento->tipo_documento,

                    'versao' => $versaoNova,

                    'nome_arquivo' => $ficheiro->getClientOriginalName(),

                    'caminho_arquivo' => $caminhoNovo,

                    'data_upload' => now(),

                    'validade' => null,

                    'estado' => 'pendente',

                    'descricao' => 'Substituição da versão '.
                        $documento->versao,
                ]);

                /*
                 * Registar a operação associada ao documento
                 * anterior, sem alterar o seu registo.
                 */
                HistoricoEstado::create([
                    'id_documento' => $documento->id_documento,

                    'estado_anterior' => $documento->estado,

                    'estado_novo' => 'substituido',

                    'motivo' => 'Substituição da versão '.
                        $documento->versao.
                        ' pela versão '.
                        $versaoNova.
                        '.',

                    'id_utilizador' => $user->id_utilizador,
                ]);

                return $novoDocumento;
            });

            return response()->json([
                'message' => 'Documento substituído com sucesso.',
                'documento' => $novoDocumento,
            ], 201);

        } catch (Throwable $e) {
            /*
             * Se a transacção falhar, remover apenas o novo
             * ficheiro criado nesta tentativa.
             */
            if ($caminhoNovo) {
                $disk->delete($caminhoNovo);
            }

            if ($e instanceof HttpException) {
                throw $e;
            }

            report($e);

            return response()->json([
                'message' => 'Não foi possível substituir o documento.',
            ], 500);
        }
    }
}
