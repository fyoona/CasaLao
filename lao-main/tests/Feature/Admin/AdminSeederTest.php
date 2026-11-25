<?php

namespace Tests\Feature\Admin;

use Database\Seeders\AdminSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_seeder_creates_a_deployment_admin_that_can_log_in(): void
    {
        config([
            'services.admin_bootstrap.name' => 'CasaLao Admin',
            'services.admin_bootstrap.email' => 'admin@example.test',
            'services.admin_bootstrap.password' => 'a-strong-deployment-password',
        ]);

        $this->seed(AdminSeeder::class);
        $this->seed(AdminSeeder::class);

        $this->assertDatabaseCount('users', 1);
        $this->assertDatabaseHas('users', [
            'email' => 'admin@example.test',
            'role' => 'admin',
        ]);

        $this->post('/login', [
            'email' => 'admin@example.test',
            'password' => 'a-strong-deployment-password',
        ])->assertRedirect(route('dashboard', absolute: false));

        $this->assertAuthenticated();
    }

    public function test_admin_seeder_fails_clearly_if_no_admin_or_credentials_exist(): void
    {
        config([
            'services.admin_bootstrap.email' => null,
            'services.admin_bootstrap.password' => null,
        ]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Set ADMIN_EMAIL and ADMIN_PASSWORD');

        $this->seed(AdminSeeder::class);
    }
}
