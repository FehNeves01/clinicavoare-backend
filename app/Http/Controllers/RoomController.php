<?php

namespace App\Http\Controllers;

use App\Models\Room;
use Illuminate\Http\JsonResponse;

class RoomController extends Controller
{
    /**
     * Lista todas as salas ativas.
     */
    public function index(): JsonResponse
    {
        return response()->json(Room::active()->get());
    }

    /**
     * Mostra os detalhes de uma sala.
     */
    public function show(int $id): JsonResponse
    {
        return response()->json(Room::findOrFail($id));
    }
}

