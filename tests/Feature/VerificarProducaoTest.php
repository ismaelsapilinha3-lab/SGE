<?php

it('falha quando a configuração ainda é de desenvolvimento', function () {
    $this->artisan('app:verificar-producao')
        ->assertFailed();
});

it('passa com a configuração mínima de produção', function () {
    config([
        'app.env' => 'production',
        'app.debug' => false,
        'app.key' => 'base64:chave-de-teste',
        'app.url' => 'https://sge.exemplo.ao',
        'app.frontend_url' => 'https://sge.exemplo.ao',
        'session.secure' => true,
        'session.encrypt' => true,
        'session.http_only' => true,
        'session.driver' => 'database',
        'queue.default' => 'database',
        'cache.default' => 'database',
        'mail.default' => 'smtp',
        'sms.enabled' => true,
        'sms.provider' => 'http',
        'backup.enabled' => true,
        'security.csp.enabled' => true,
    ]);

    $this->artisan('app:verificar-producao')
        ->expectsOutputToContain('Configuração mínima de produção validada.')
        ->assertSuccessful();
});

it('aceita sms desativado com o provedor simulado', function () {
    config([
        'app.env' => 'production',
        'app.debug' => false,
        'app.key' => 'base64:chave-de-teste',
        'app.url' => 'https://sge.exemplo.ao',
        'app.frontend_url' => 'https://sge.exemplo.ao',
        'session.secure' => true,
        'session.encrypt' => true,
        'session.http_only' => true,
        'session.driver' => 'database',
        'queue.default' => 'database',
        'cache.default' => 'database',
        'mail.default' => 'smtp',
        'sms.enabled' => false,
        'sms.provider' => 'simulado',
        'backup.enabled' => true,
        'security.csp.enabled' => true,
    ]);

    $this->artisan('app:verificar-producao')
        ->assertSuccessful();
});

it('exige que o portal use https', function () {
    config([
        'app.env' => 'production',
        'app.debug' => false,
        'app.key' => 'base64:chave-de-teste',
        'app.url' => 'https://sge.exemplo.ao',
        'app.frontend_url' => 'http://sge.exemplo.ao',
        'session.secure' => true,
        'session.encrypt' => true,
        'session.http_only' => true,
        'session.driver' => 'database',
        'queue.default' => 'database',
        'cache.default' => 'database',
        'mail.default' => 'smtp',
        'sms.enabled' => false,
        'sms.provider' => 'simulado',
        'backup.enabled' => true,
        'security.csp.enabled' => true,
    ]);

    $this->artisan('app:verificar-producao')
        ->expectsOutputToContain('URL do portal')
        ->assertFailed();
});

it('exige a csp ativa em produção', function () {
    config([
        'app.env' => 'production',
        'app.debug' => false,
        'app.key' => 'base64:chave-de-teste',
        'app.url' => 'https://sge.exemplo.ao',
        'app.frontend_url' => 'https://sge.exemplo.ao',
        'session.secure' => true,
        'session.encrypt' => true,
        'session.http_only' => true,
        'session.driver' => 'database',
        'queue.default' => 'database',
        'cache.default' => 'database',
        'mail.default' => 'smtp',
        'sms.enabled' => false,
        'sms.provider' => 'simulado',
        'backup.enabled' => true,
        'security.csp.enabled' => false,
    ]);

    $this->artisan('app:verificar-producao')
        ->expectsOutputToContain('CSP')
        ->assertFailed();
});
