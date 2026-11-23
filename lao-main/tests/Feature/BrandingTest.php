<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BrandingTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_and_guest_pages_use_the_casa_lao_brand(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee('<title>CasaLao</title>', false);

        $this->get('/login')
            ->assertOk()
            ->assertSee('<title>CasaLao</title>', false);
    }

    public function test_authenticated_pages_use_the_casa_lao_brand(): void
    {
        $this->actingAs(User::factory()->create())
            ->get('/profile')
            ->assertOk()
            ->assertSee('<title>CasaLao</title>', false);
    }
}
