<?php

namespace App\Http\Controllers;

use App\Models\Client;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class ClientController extends Controller
{
    /**
     * Lista clientes com busca e paginação opcionais.
     */
    public function index(Request $request)
    {
        $query = Client::query();

        if ($search = $request->string('search')->trim()->toString()) {
            $query->where(function ($builder) use ($search) {
                $builder->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%");
            });
        }

        $perPage = (int) $request->input('per_page', 15);
        $perPage = max(1, min($perPage, 100));

        return $query->orderBy('name')->paginate($perPage);
    }

    /**
     * Cria um novo cliente.
     */
    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:clients,email'],
            'phone' => ['nullable', 'string', 'max:30'],
            'birth_date' => ['nullable', 'date'],
            'credit_balance' => ['nullable', 'numeric', 'min:0'],
            'credit_consumed' => ['nullable', 'numeric', 'min:0'],
            'credit_expires_at' => ['nullable', 'date'],
        ]);

        $client = Client::create($data);

        return response()->json($client, Response::HTTP_CREATED);
    }

    /**
     * Mostra os detalhes de um cliente.
     */
    public function show(Client $client)
    {
        $client->checkAndExpireCredits();
        $client->refresh();

        return $client;
    }

    /**
     * Atualiza um cliente existente.
     */
    public function update(Request $request, Client $client)
    {
        $data = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'email' => ['sometimes', 'required', 'email', 'max:255', "unique:clients,email,{$client->id}"],
            'phone' => ['sometimes', 'nullable', 'string', 'max:30'],
            'birth_date' => ['sometimes', 'nullable', 'date'],
            'credit_balance' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'credit_consumed' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'credit_expires_at' => ['sometimes', 'nullable', 'date'],
        ]);

        $client->fill($data);
        $client->save();

        return $client->fresh();
    }

    /**
     * Remove um cliente.
     */
    public function destroy(Client $client)
    {
        $client->delete();

        return response()->noContent();
    }
}
