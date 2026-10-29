<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Throwable;

class PasswordResetController extends Controller
{
    /**
     * Envia um link de recuperação para o e-mail indicado.
     *
     * A resposta é sempre a mesma, exista ou não uma conta com o e-mail,
     * para não revelar quais os endereços registados.
     */
    public function enviarLink(Request $request)
    {
        $dados = $request->validate([
            'email' => ['required', 'email', 'max:255'],
        ]);

        try {
            Password::broker()->sendResetLink($dados);
        } catch (Throwable $excecao) {
            Log::error(
                'Falha ao enviar o e-mail de recuperação de palavra-passe.',
                ['erro' => $excecao->getMessage()]
            );
        }

        return response()->json([
            'message' => 'Se existir uma conta com esse e-mail, enviámos um link para redefinir a palavra-passe.',
        ]);
    }

    /**
     * Redefine a palavra-passe com o token enviado por e-mail.
     */
    public function redefinir(Request $request)
    {
        $dados = $request->validate([
            'token' => ['required', 'string'],
            'email' => ['required', 'email', 'max:255'],
            'password' => [
                'required',
                'string',
                PasswordRule::default(),
                'confirmed',
            ],
        ]);

        $estado = Password::broker()->reset(
            $dados,
            function (User $utilizador, string $password): void {
                $utilizador->forceFill(['password' => $password])->save();

                /*
                 * Um token de sessão antigo não deve sobreviver à alteração
                 * da palavra-passe: quem tiver acesso ao token perde a sessão.
                 */
                $utilizador->tokens()->delete();
            }
        );

        if ($estado === Password::PASSWORD_RESET) {
            return response()->json([
                'message' => 'Palavra-passe redefinida com sucesso. Já podes iniciar sessão.',
            ]);
        }

        return response()->json([
            'message' => 'O link de recuperação é inválido ou expirou. Pede um novo link.',
        ], 422);
    }
}
