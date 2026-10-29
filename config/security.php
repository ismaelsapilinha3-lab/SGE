<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Content Security Policy
    |--------------------------------------------------------------------------
    |
    | A CSP é aplicada às respostas do Laravel. Fica desativada por omissão
    | em desenvolvimento para não interferir com o Vite; em produção deve
    | ser ativada e validada com app:verificar-producao.
    |
    */

    'csp' => [
        'enabled' => (bool) env('CSP_ENABLED', false),

        /*
         * Quando ativo, o cabeçalho é enviado como
         * Content-Security-Policy-Report-Only, sem bloquear pedidos.
         * Útil para testar a política antes de a impor.
         */
        'report_only' => (bool) env('CSP_REPORT_ONLY', false),
    ],

];
