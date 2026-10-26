<?php

it('aplica cabeçalhos de segurança às respostas', function () {
    $this->get('/up')
        ->assertOk()
        ->assertHeader('X-Content-Type-Options', 'nosniff')
        ->assertHeader('X-Frame-Options', 'DENY')
        ->assertHeader(
            'Referrer-Policy',
            'strict-origin-when-cross-origin'
        )
        ->assertHeader(
            'Permissions-Policy',
            'camera=(), microphone=(), geolocation=()'
        )
        ->assertHeaderMissing('Strict-Transport-Security');
});

it('envia hsts em pedidos https', function () {
    $this->get('https://localhost/up')
        ->assertOk()
        ->assertHeader(
            'Strict-Transport-Security',
            'max-age=31536000; includeSubDomains'
        );
});

it('limita pedidos repetidos às rotas públicas', function () {
    for ($pedido = 0; $pedido < 60; $pedido++) {
        $this->getJson('/api/departamentos')->assertOk();
    }

    $this->getJson('/api/departamentos')->assertStatus(429);
});
