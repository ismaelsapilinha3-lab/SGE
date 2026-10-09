<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $user = User::where('email', $credentials['email'])->first();

        if (
            !$user ||
            !$user->ativo ||
            !Hash::check($credentials['password'], $user->password)
        ) {
            throw ValidationException::withMessages([
                'email' => ['E-mail ou palavra-passe incorretos.'],
            ]);
        }

        $token = $user
            ->createToken('angular-sge')
            ->plainTextToken;

        return response()->json([
            'token' => $token,

            'user' => [
                'id_utilizador' => $user->id_utilizador,
                'nome' => $user->nome,
                'email' => $user->email,
                'tipo_utilizador' => $user->tipo_utilizador,
            ],
        ]);
    }

    public function logout(Request $request)
    {
        $token = $request->user()->currentAccessToken();

        if ($token) {
            $token->delete();
        }

        return response()->json([
            'message' => 'Sessão encerrada com sucesso.'
        ]);
    }
}



