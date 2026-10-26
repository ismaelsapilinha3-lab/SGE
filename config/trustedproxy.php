<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Proxies de confiança
    |--------------------------------------------------------------------------
    |
    | Lista de endereços IP (ou "*") dos proxies reversos que podem enviar
    | os cabeçalhos X-Forwarded-*. Sem esta configuração, todos os pedidos
    | atrás de um proxy parecem vir do mesmo IP.
    |
    */

    'proxies' => env('TRUSTED_PROXIES'),

];
