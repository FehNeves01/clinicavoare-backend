<?php

namespace App\Http\Controllers;

use App\Models\Client;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CreditController extends Controller
{
    /**
     * Retorna o saldo de créditos de um cliente.
     */
    public function balance(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'client_id' => ['required', 'exists:clients,id'],
        ]);

        $client = Client::findOrFail($validated['client_id']);
        $client->checkAndExpireCredits();

        return response()->json([
            'balance' => $client->credit_balance,
            'consumed' => $client->credit_consumed,
            'expires_at' => $client->credit_expires_at,
        ]);
    }
}

