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
     * Log seguro que não quebra o fluxo se houver erro de permissão
     */
    private function safeLog(string $level, string $message, array $context = []): void
    {
        try {
            logger()->{$level}($message, $context);
        } catch (\Throwable $e) {
            // Silenciosamente ignora erros de log (ex: permissão)
        }
    }

    /**
     * Handle an authentication attempt and retrieve a Passport access token.
     */
    public function login(Request $request)
    {
        $this->safeLog('info', '=== INÍCIO DO PROCESSO DE LOGIN ===', [
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'origin' => $request->header('Origin'),
            'referer' => $request->header('Referer'),
        ]);

        try {
            $credentials = $request->validate([
                'email' => ['required', 'email'],
                'password' => ['required', 'string'],
            ]);

            logger()->info('Credenciais validadas', [
                'email' => $credentials['email'],
                'password_length' => strlen($credentials['password']),
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            logger()->error('Erro de validação nas credenciais', [
                'errors' => $e->errors(),
            ]);
            throw $e;
        }

        // Buscar configuração do client
        $clientId = config('services.passport.client_id');
        $clientSecret = config('services.passport.client_secret');
        $endpoint = config('services.passport.login_endpoint');

        logger()->info('Configuração inicial do Passport', [
            'client_id_from_config' => $clientId ? 'configurado' : 'não configurado',
            'client_secret_from_config' => $clientSecret ? 'configurado' : 'não configurado',
            'endpoint_from_config' => $endpoint,
            'app_url' => config('app.url'),
            'request_host' => $request->getSchemeAndHttpHost(),
        ]);

        // Se não houver endpoint configurado, usa a URL da aplicação atual
        if (blank($endpoint) || Str::contains($endpoint, 'localhost') || Str::contains($endpoint, 'voare.test')) {
            $appUrl = config('app.url', $request->getSchemeAndHttpHost());
            $endpoint = rtrim($appUrl, '/') . '/oauth/token';
            logger()->info('Endpoint OAuth construído automaticamente', [
                'endpoint' => $endpoint,
                'app_url_used' => $appUrl,
            ]);
        } else {
            logger()->info('Endpoint OAuth usando configuração', [
                'endpoint' => $endpoint,
            ]);
        }

        // Buscar client do banco se não estiver configurado
        if (blank($clientId) || blank($clientSecret)) {
            logger()->info('Buscando client do tipo password no banco de dados');

            $passwordClient = Client::whereJsonContains('grant_types', 'password')
                ->where('revoked', false)
                ->orderByDesc('created_at')
                ->first();

            if ($passwordClient) {
                $clientId = $passwordClient->id;
                $clientSecret = $passwordClient->secret;
                logger()->info('Client encontrado no banco de dados', [
                    'client_id' => $clientId,
                    'client_name' => $passwordClient->name ?? 'N/A',
                    'client_secret_length' => strlen($clientSecret),
                ]);
            } else {
                logger()->warning('Nenhum client do tipo password encontrado no banco', [
                    'total_clients' => Client::count(),
                    'revoked_clients' => Client::where('revoked', true)->count(),
                ]);
            }
        }

        if (!$clientId || !$clientSecret) {
            logger()->error('Configuração OAuth do servidor ausente', [
                'client_id' => $clientId ? 'existe' : 'ausente',
                'client_secret' => $clientSecret ? 'existe' : 'ausente',
            ]);

            return response()->json([
                'message' => 'Configuração OAuth do servidor ausente.',
            ], 500);
        }

        logger()->info('Preparando requisição para endpoint OAuth', [
            'endpoint' => $endpoint,
            'client_id' => $clientId,
            'grant_type' => 'password',
            'username' => $credentials['email'],
        ]);

        try {
            $response = Http::asForm()->withoutVerifying()->post($endpoint, [
                'grant_type' => 'password',
                'client_id' => $clientId,
                'client_secret' => $clientSecret,
                'username' => $credentials['email'],
                'password' => $credentials['password'],
                'scope' => '*',
            ]);

            logger()->info('Resposta recebida do endpoint OAuth', [
                'status_code' => $response->status(),
                'success' => $response->successful(),
                'failed' => $response->failed(),
                'response_body' => $response->body(),
            ]);
        } catch (\Throwable $exception) {
            logger()->error('Erro ao contatar servidor de autenticação', [
                'endpoint' => $endpoint,
                'error' => $exception->getMessage(),
                'error_class' => get_class($exception),
                'trace' => $exception->getTraceAsString(),
            ]);

            return response()->json([
                'message' => 'Erro ao contatar o servidor de autenticação.',
                'error' => $exception->getMessage(),
            ], 500);
        }

        if ($response->failed()) {
            $status = $response->status();
            $responseBody = $response->json();
            $responseText = $response->body();

            logger()->error('Resposta falhou do endpoint OAuth', [
                'status_code' => $status,
                'response_body' => $responseBody,
                'response_text' => $responseText,
                'headers' => $response->headers(),
            ]);

            return response()->json([
                'message' => $response->json('message', $response->json('error_description', 'Credenciais inválidas.')),
                'error' => $response->json('error'),
                'error_description' => $response->json('error_description'),
                'errors' => $response->json('errors', []),
            ], in_array($status, [400, 401], true) ? 401 : $status);
        }

        $tokenData = $response->json();

        logger()->info('Token recebido do OAuth', [
            'has_access_token' => isset($tokenData['access_token']),
            'has_refresh_token' => isset($tokenData['refresh_token']),
            'token_type' => $tokenData['token_type'] ?? null,
            'expires_in' => $tokenData['expires_in'] ?? null,
        ]);

        if (!isset($tokenData['access_token'])) {
            logger()->error('Resposta de autenticação inválida - sem access_token', [
                'token_data_keys' => array_keys($tokenData),
                'token_data' => $tokenData,
            ]);

            return response()->json([
                'message' => 'Resposta de autenticação inválida.',
            ], 500);
        }

        logger()->info('Buscando usuário no banco de dados', [
            'email' => $credentials['email'],
        ]);

        $user = User::where('email', $credentials['email'])->first();

        if ($user) {
            logger()->info('Usuário encontrado', [
                'user_id' => $user->id,
                'user_name' => $user->name,
            ]);

            $user->loadMissing(['roles', 'permissions']);

            logger()->info('Roles e permissions carregadas', [
                'roles_count' => $user->roles->count(),
                'permissions_count' => $user->permissions->count(),
            ]);
        } else {
            logger()->warning('Usuário não encontrado no banco de dados', [
                'email' => $credentials['email'],
            ]);
        }

        logger()->info('=== LOGIN CONCLUÍDO COM SUCESSO ===', [
            'user_id' => $user?->id,
            'token_expires_in' => $tokenData['expires_in'] ?? null,
        ]);

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

        // Se não houver endpoint configurado, usa a URL da aplicação atual
        if (blank($endpoint) || Str::contains($endpoint, 'localhost') || Str::contains($endpoint, 'voare.test')) {
            $appUrl = config('app.url', $request->getSchemeAndHttpHost());
            $endpoint = rtrim($appUrl, '/') . '/oauth/token';
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


