<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Laravel\Passport\Client;

class AuthController extends Controller
{
    /**
     * Handle an authentication attempt and retrieve a Passport access token.
     */
    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $clientId = config('passport.password_client_id');
        $clientSecret = config('passport.password_client_secret');
        $endpoint = config('passport.login_endpoint', config('app.url') . '/oauth/token');
       
       
        if (blank($endpoint) || Str::contains($endpoint, 'localhost')) {
            $endpoint = 'https://voare.test/oauth/token';
        }

        if (blank($clientId) || blank($clientSecret)) {
            $passwordClient = Client::whereJsonContains('grant_types', 'password')
                ->where('revoked', false)
                ->orderByDesc('created_at')
                ->first();

            if ($passwordClient) {
                $clientId = $passwordClient->id;
                $clientSecret = $passwordClient->secret;
            }
        }

        if (!$clientId || !$clientSecret) {
            return response()->json([
                'message' => 'Configuração OAuth do servidor ausente.',
            ], 500);
        }

        try {
            $response = Http::asForm()->withoutVerifying()->post($endpoint, [
                'grant_type' => 'password',
                'client_id' => $clientId,
                'client_secret' => $clientSecret,
                'username' => $credentials['email'],
                'password' => $credentials['password'],
                'scope' => '*',
            ]);
        } catch (\Throwable $exception) {
            return response()->json([
                'message' => 'Erro ao contatar o servidor de autenticação.',
            ], 500);
        }

        if ($response->failed()) {
            $status = $response->status();

            return response()->json([
                'message' => $response->json('message', 'Credenciais inválidas.'),
                'errors' => $response->json('errors', []),
            ], in_array($status, [400, 401], true) ? 401 : $status);
        }

        $tokenData = $response->json();

        if (!isset($tokenData['access_token'])) {
            return response()->json([
                'message' => 'Resposta de autenticação inválida.',
            ], 500);
        }

        $user = User::where('email', $credentials['email'])->first();

        if ($user) {
            $user->loadMissing(['roles', 'permissions']);
        }

        return response()->json([
            'token_type' => $tokenData['token_type'] ?? 'Bearer',
            'expires_in' => $tokenData['expires_in'] ?? null,
            'access_token' => $tokenData['access_token'],
            'refresh_token' => $tokenData['refresh_token'] ?? null,
            'user' => $user,
        ]);
    }

    /**
     * Refresh an access token using a refresh token.
     */
    public function refresh(Request $request)
    {
        $request->validate([
            'refresh_token' => ['required', 'string'],
        ]);

        $clientId = config('services.passport.client_id');
        $clientSecret = config('services.passport.client_secret');
        $endpoint = config('services.passport.login_endpoint');

        if (blank($endpoint) || Str::contains($endpoint, 'localhost')) {
            $endpoint = 'https://voare.test/oauth/token';
        }

        if (blank($clientId) || blank($clientSecret)) {
            $passwordClient = Client::whereJsonContains('grant_types', 'password')
                ->where('revoked', false)
                ->orderByDesc('created_at')
                ->first();

            if ($passwordClient) {
                $clientId = $passwordClient->id;
                $clientSecret = $passwordClient->secret;
            }
        }

        if (!$clientId || !$clientSecret) {
            return response()->json([
                'message' => 'Configuração OAuth do servidor ausente.',
            ], 500);
        }

        try {
            $response = Http::asForm()->withoutVerifying()->post($endpoint, [
                'grant_type' => 'refresh_token',
                'refresh_token' => $request->refresh_token,
                'client_id' => $clientId,
                'client_secret' => $clientSecret,
                'scope' => '*',
            ]);
        } catch (\Throwable $exception) {
            logger()->error('Erro ao renovar token: ' . $exception->getMessage());
            return response()->json([
                'message' => 'Erro ao renovar token de acesso.',
            ], 500);
        }

        if ($response->failed()) {
            $status = $response->status();
            return response()->json([
                'message' => $response->json('message', 'Refresh token inválido ou expirado.'),
                'errors' => $response->json('errors', []),
            ], in_array($status, [400, 401], true) ? 401 : $status);
        }

        $tokenData = $response->json();

        if (!isset($tokenData['access_token'])) {
            return response()->json([
                'message' => 'Resposta de renovação inválida.',
            ], 500);
        }

        // Buscar usuário associado ao token
        $user = null;
        try {
            // Decodificar o token para obter o user_id
            $tokenParts = explode('.', $tokenData['access_token']);
            if (count($tokenParts) === 3) {
                $payload = json_decode(base64_decode(str_replace(['-', '_'], ['+', '/'], $tokenParts[1])), true);
                $userId = $payload['sub'] ?? null;

                if ($userId) {
                    $user = User::find($userId);
                    if ($user) {
                        $user->loadMissing(['roles', 'permissions']);
                    }
                }
            }

            // Fallback: tentar obter via API se decodificação falhar
            if (!$user) {
                $tokenResponse = Http::withoutVerifying()
                    ->withToken($tokenData['access_token'])
                    ->get('https://voare.test/api/user');

                if ($tokenResponse->successful()) {
                    $user = $tokenResponse->json();
                }
            }
        } catch (\Throwable $e) {
            // Se não conseguir, não é crítico - o token ainda é válido
            logger()->debug('Não foi possível obter dados do usuário durante refresh', [
                'error' => $e->getMessage(),
            ]);
        }

        return response()->json([
            'token_type' => $tokenData['token_type'] ?? 'Bearer',
            'expires_in' => $tokenData['expires_in'] ?? null,
            'access_token' => $tokenData['access_token'],
            'refresh_token' => $tokenData['refresh_token'] ?? null,
            'user' => $user,
        ]);
    }

    /**
     * Revoke the current user's access token and refresh token.
     */
    public function logout(Request $request)
    {
        $user = $request->user();

        if ($user) {
            // Revogar todos os tokens do usuário
            DB::table('oauth_access_tokens')
                ->where('user_id', $user->id)
                ->update(['revoked' => true]);
        }

        // Também revogar refresh tokens se houver
        $refreshToken = $request->input('refresh_token');
        if ($refreshToken) {
            try {
                DB::table('oauth_refresh_tokens')
                    ->where('refresh_token', hash('sha256', $refreshToken))
                    ->update(['revoked' => true]);
            } catch (\Throwable $e) {
                logger()->debug('Erro ao revogar refresh token', [
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return response()->json([
            'message' => 'Logout realizado com sucesso.',
        ]);
    }

    /**
     * Register a new user.
     */
    public function register(Request $request)
    {
        // TODO: Implementar registro de usuário
        return response()->json(['message' => 'Register endpoint']);
    }

    /**
     * Get the authenticated user.
     */
    public function user(Request $request)
    {
        $user = $request->user();
        if ($user) {
            $user->loadMissing(['roles', 'permissions']);
        }
        return $user;
    }
}


