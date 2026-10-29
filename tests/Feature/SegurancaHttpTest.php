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

it('aplica a política de segurança de conteúdos quando ativada', function () {
    config([
        'security.csp.enabled' => true,
        'security.csp.report_only' => false,
        'app.url' => 'https://api.sge.exemplo.ao',
        'app.frontend_url' => 'https://sge.exemplo.ao',
    ]);

    $resposta = $this->get('/up')->assertOk();

    $politica = $resposta->headers->get('Content-Security-Policy');

    expect($politica)
        ->toContain("default-src 'self'")
        ->toContain("script-src 'self'")
        ->toContain("object-src 'none'")
        ->toContain("frame-ancestors 'none'")
        ->toContain("connect-src 'self'")
        ->toContain('https://api.sge.exemplo.ao')
        ->toContain('https://sge.exemplo.ao')
        ->not->toContain('upgrade-insecure-requests');
});

it('usa a csp em modo report-only quando configurado', function () {
    config([
        'security.csp.enabled' => true,
        'security.csp.report_only' => true,
    ]);

    $resposta = $this->get('/up')->assertOk();

    $resposta->assertHeaderMissing('Content-Security-Policy');

    expect(
        $resposta->headers->get('Content-Security-Policy-Report-Only')
    )->toContain("default-src 'self'");
});

it('não envia csp quando desativada', function () {
    config(['security.csp.enabled' => false]);

    $this->get('/up')
        ->assertOk()
        ->assertHeaderMissing('Content-Security-Policy')
        ->assertHeaderMissing('Content-Security-Policy-Report-Only');
});

it('limita pedidos repetidos às rotas públicas', function () {
    for ($pedido = 0; $pedido < 60; $pedido++) {
        $this->getJson('/api/departamentos')->assertOk();
    }

    $this->getJson('/api/departamentos')->assertStatus(429);
});
