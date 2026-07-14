<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $users = [
            ['name' => 'Jeralth Contreras', 'username' => 'Sistemas.Jeralthc', 'email' => 'admin.jeralthc@gmail.com', 'role' => 'admin'],
            ['name' => 'Admin', 'username' => 'General.Admin2', 'email' => 'admin@Empresa Base.com', 'role' => 'admin'],
            ['name' => 'Juan Perez', 'username' => 'Compras.Juan', 'email' => 'jperez@gmail.com', 'role' => 'comprador'],
            ['name' => 'Ana Suarez', 'username' => 'Recepcion.Ana', 'email' => 'asuarez@gmail.com', 'role' => 'receptor'],
            ['name' => 'Miguel Peña', 'username' => 'J070014733', 'email' => 'soporte@tuempresa.com', 'role' => 'proveedor'],
            ['name' => 'Luis Echeverria', 'username' => 'E844767415', 'email' => 'luisecheverria25@gmail.com', 'role' => 'proveedor'],
            ['name' => 'Leonardo Carrero', 'username' => 'J505302930', 'email' => 'leonardocarr@gmail.com', 'role' => 'proveedor'],
        ];

        foreach ($users as $userData) {
            $password = in_array($userData['username'], ['Recepcion.Ana', 'Compras.Juan']) ? 'Empresa Base.2026' : 'Empresa Base2026';
            User::updateOrCreate(
                ['username' => $userData['username']],
                [
                    'name' => $userData['name'],
                    'email' => $userData['email'],
                    'role' => $userData['role'],
                    'password' => \Illuminate\Support\Facades\Hash::make($password),
                ]
            );
        }
    }
}
