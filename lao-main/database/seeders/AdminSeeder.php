<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use RuntimeException;

class AdminSeeder extends Seeder
{
    public function run(): void
    {
        $email = config('services.admin_bootstrap.email');
        $password = config('services.admin_bootstrap.password');

        if (blank($email) && blank($password)) {
            if (User::where('role', 'admin')->exists()) {
                return;
            }

            throw new RuntimeException('Set ADMIN_EMAIL and ADMIN_PASSWORD in the deployment environment to create the initial admin account.');
        }

        if (blank($email) || blank($password)) {
            throw new RuntimeException('Both ADMIN_EMAIL and ADMIN_PASSWORD must be set to create the initial admin account.');
        }

        if (! is_string($email) || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            throw new RuntimeException('ADMIN_EMAIL must be a valid email address.');
        }

        $name = config('services.admin_bootstrap.name');

        User::updateOrCreate(
            ['email' => $email],
            [
                'name' => is_string($name) && filled($name) ? $name : 'CasaLao Admin',
                'password' => $password,
                'role' => 'admin',
            ],
        );
    }
}
