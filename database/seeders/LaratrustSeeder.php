<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Role;
use App\Models\Permission;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class LaratrustSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Criar Permissions
        $permissions = [
            // Usuários
            ['name' => 'users.create', 'display_name' => 'Criar Usuários', 'description' => 'Permite criar novos usuários'],
            ['name' => 'users.read', 'display_name' => 'Visualizar Usuários', 'description' => 'Permite visualizar usuários'],
            ['name' => 'users.update', 'display_name' => 'Editar Usuários', 'description' => 'Permite editar usuários'],
            ['name' => 'users.delete', 'display_name' => 'Excluir Usuários', 'description' => 'Permite excluir usuários'],
            ['name' => 'users.manage-credits', 'display_name' => 'Gerenciar Créditos', 'description' => 'Permite adicionar/remover créditos de usuários'],

            // Salas
            ['name' => 'rooms.create', 'display_name' => 'Criar Salas', 'description' => 'Permite criar novas salas'],
            ['name' => 'rooms.read', 'display_name' => 'Visualizar Salas', 'description' => 'Permite visualizar salas'],
            ['name' => 'rooms.update', 'display_name' => 'Editar Salas', 'description' => 'Permite editar salas'],
            ['name' => 'rooms.delete', 'display_name' => 'Excluir Salas', 'description' => 'Permite excluir salas'],

            // Agendamentos
            ['name' => 'bookings.create', 'display_name' => 'Criar Agendamentos', 'description' => 'Permite criar agendamentos'],
            ['name' => 'bookings.read', 'display_name' => 'Visualizar Agendamentos', 'description' => 'Permite visualizar agendamentos'],
            ['name' => 'bookings.update', 'display_name' => 'Editar Agendamentos', 'description' => 'Permite editar agendamentos'],
            ['name' => 'bookings.delete', 'display_name' => 'Cancelar Agendamentos', 'description' => 'Permite cancelar agendamentos'],
            ['name' => 'bookings.manage-all', 'display_name' => 'Gerenciar Todos Agendamentos', 'description' => 'Permite gerenciar agendamentos de todos os usuários'],

            // Relatórios
            ['name' => 'reports.view', 'display_name' => 'Visualizar Relatórios', 'description' => 'Permite visualizar relatórios do sistema'],

            // Sistema
            ['name' => 'system.manage', 'display_name' => 'Gerenciar Sistema', 'description' => 'Acesso total ao sistema'],
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(
                ['name' => $permission['name']],
                [
                    'display_name' => $permission['display_name'],
                    'description' => $permission['description'],
                ]
            );
        }

        // Criar Roles
        $adminRole = Role::firstOrCreate(
            ['name' => 'admin'],
            [
                'display_name' => 'Administrador',
                'description' => 'Acesso total ao sistema com todas as permissões',
            ]
        );

        $userRole = Role::firstOrCreate(
            ['name' => 'user'],
            [
                'display_name' => 'Usuário',
                'description' => 'Usuário comum do sistema',
            ]
        );

        // Atribuir todas as permissions ao role admin
        $allPermissions = Permission::all();
        $adminRole->permissions()->sync($allPermissions->pluck('id'));

        // Atribuir permissions básicas ao role user
        $userPermissions = Permission::whereIn('name', [
            'rooms.read',
            'bookings.create',
            'bookings.read',
            'bookings.delete', // Pode cancelar seus próprios agendamentos
        ])->pluck('id');
        $userRole->permissions()->sync($userPermissions);

        // Criar usuário administrador
        $admin = User::firstOrCreate(
            ['email' => 'admin@clinicavoare.com'],
            [
                'name' => 'Administrador',
                'password' => Hash::make('admin123'),
            ]
        );

        // Atribuir role admin ao usuário administrador
        $admin->addRole($adminRole);

        $this->command->info('✅ Laratrust configurado com sucesso!');
        $this->command->info('👤 Usuário administrador criado:');
        $this->command->info('   Email: admin@clinicavoare.com');
        $this->command->info('   Senha: admin123');
        $this->command->info('   Role: admin (com todas as permissões)');
    }
}

