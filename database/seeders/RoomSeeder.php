<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Room;

class RoomSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $rooms = [
            [
                'number' => '101',
                'name' => 'Sala Executiva A',
                'description' => 'Sala ideal para reuniões executivas com capacidade para até 8 pessoas. Equipada com projetor, TV e ar condicionado.',
                'capacity' => 8,
                'is_active' => true,
            ],
            [
                'number' => '102',
                'name' => 'Sala de Reunião B',
                'description' => 'Espaço confortável para reuniões médias. Possui quadro branco e sistema de videoconferência.',
                'capacity' => 12,
                'is_active' => true,
            ],
            [
                'number' => '103',
                'name' => 'Sala de Treinamento',
                'description' => 'Sala ampla ideal para treinamentos e workshops. Layout flexível com mesas móveis.',
                'capacity' => 20,
                'is_active' => true,
            ],
            [
                'number' => '201',
                'name' => 'Sala Coworking',
                'description' => 'Espaço colaborativo com ambiente descontraído. Mesa compartilhada e boa iluminação.',
                'capacity' => 6,
                'is_active' => true,
            ],
            [
                'number' => '202',
                'name' => 'Sala Privativa',
                'description' => 'Sala individual para trabalho focado ou atendimentos. Silenciosa e com internet de alta velocidade.',
                'capacity' => 2,
                'is_active' => true,
            ],
            [
                'number' => '203',
                'name' => 'Auditório',
                'description' => 'Grande espaço para eventos, palestras e apresentações. Sistema de som profissional.',
                'capacity' => 50,
                'is_active' => true,
            ],
        ];

        foreach ($rooms as $room) {
            Room::create($room);
        }

        $this->command->info('Salas criadas com sucesso!');
    }
}
