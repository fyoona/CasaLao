<?php

namespace Tests\Feature;

use Tests\TestCase;

class ForwardedHttpsTest extends TestCase
{
    public function test_urls_use_https_when_the_request_is_forwarded_by_a_tls_proxy(): void
    {
        $this->get('/', [
            'X-Forwarded-Proto' => 'https',
            'X-Forwarded-Port' => '443',
        ])
            ->assertOk()
            ->assertSee('https://localhost/login', false)
            ->assertSee('https://localhost/register', false)
            ->assertSee('href="https://localhost/build/assets/', false);
    }
}
