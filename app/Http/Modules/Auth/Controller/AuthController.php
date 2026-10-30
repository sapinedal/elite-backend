<?php

namespace App\Http\Modules\Auth\Controller;

use App\Http\Controllers\Controller;
use App\Http\Modules\Auth\Request\ResetPasswordRequest;
use App\Http\Modules\Auth\Service\PasswordResetService;
use App\Http\Modules\Users\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

class AuthController extends Controller
{
    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        $user = User::where('email', $request->email)->first();

        if (! $user || ! Hash::check($request->password, $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['Las credenciales proporcionadas son incorrectas.'],
            ]);
        }

        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'token' => $token,
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
            ]
        ]);
    }

    /**
     * Envía el enlace para restablecer la contraseña. La respuesta es la misma exista o no el correo.
     */
    public function forgotPassword(Request $request, PasswordResetService $service): JsonResponse
    {
        $request->validate(['email' => 'required|email']);
        $service->sendLink($request->input('email'));

        return response()->json([
            'status' => 'success',
            'message' => 'Si el correo está registrado en Elite, te enviamos un enlace para restablecer la contraseña. Revisa tu bandeja de entrada y la carpeta de spam.',
        ]);
    }

    public function resetPassword(ResetPasswordRequest $request, PasswordResetService $service): JsonResponse
    {
        try {
            $service->reset($request->validated('email'), $request->validated('token'), $request->validated('password'));
        } catch (InvalidArgumentException $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 422);
        }

        return response()->json(['status' => 'success', 'message' => 'Tu contraseña se actualizó. Ya puedes ingresar con la nueva.']);
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Logged out successfully']);
    }
}
