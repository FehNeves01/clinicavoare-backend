<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Client;

class ClientSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $clients = [
            [
                'name' => 'Empresa Alpha',
                'email' => 'contato@alpha.com',
                'phone' => '1133445566',
                'birth_date' => '2000-05-15',
                'credit_balance' => 25,
                'credit_consumed' => 0,
                'credit_expires_at' => now()->endOfMonth(),
            ],
            [
                'name' => 'Consultoria Beta',
                'email' => 'contato@beta.com',
                'phone' => '1199887766',
                'birth_date' => '1995-03-10',
                'credit_balance' => 40,
                'credit_consumed' => 0,
                'credit_expires_at' => now()->endOfMonth(),
            ],
            [
                'name' => 'Startup Gama',
                'email' => 'hello@gama.io',
                'phone' => '11912344321',
                'birth_date' => '1992-11-30',
                'credit_balance' => 12,
                'credit_consumed' => 0,
                'credit_expires_at' => now()->endOfMonth(),
            ],
        ];

        foreach ($clients as $client) {
            Client::updateOrCreate(
                ['email' => $client['email']],
                $client
            );
        }

        $this->command?->info('Clientes base criados com sucesso!');
    }
}

