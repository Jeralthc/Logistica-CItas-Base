<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\User;

$users = User::all();
foreach ($users as $user) {
    if (empty($user->username)) {
        if ($user->email === 'jeralthc@gmail.com') {
            $user->username = 'Sistemas.Jeralthc';
            $user->role = 'admin';
        } else {
            // Generar un username basado en su rol y nombre
            $parts = explode(' ', trim($user->name));
            $nombre = ucfirst(strtolower($parts[0]));
            if ($user->role === 'comprador') {
                $user->username = 'Compras.' . $nombre;
            } elseif ($user->role === 'receptor') {
                $user->username = 'Recepcion.' . $nombre;
            } else {
                $user->username = 'General.' . $nombre . $user->id;
            }
        }
        $user->save();
        echo "Updated: {$user->email} -> {$user->username} (Role: {$user->role})\n";
    }
}

// Asegurarse de que jeralthc@gmail.com sea admin incluso si ya tiene username
$admin = User::where('email', 'jeralthc@gmail.com')->first();
if ($admin) {
    $admin->role = 'admin';
    if (empty($admin->username) || strpos($admin->username, '@') !== false) {
         $admin->username = 'Sistemas.Jeralthc';
    }
    $admin->save();
    echo "Admin jeralthc@gmail.com verified.\n";
} else {
    echo "Admin jeralthc@gmail.com no existe en la BD.\n";
}
