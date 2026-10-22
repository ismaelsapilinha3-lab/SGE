<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Envio de SMS
    |--------------------------------------------------------------------------
    |
    | Enquanto o Gateway oficial da MULTITEL não estiver ligado, o sistema
    | usa um provedor de teste: a mensagem fica registada na base de dados
    | (tabela sms_envios) e no log, sem qualquer custo ou envio real.
    |
    */

    'enabled' => env('SMS_ENABLED', true),

    /*
    |--------------------------------------------------------------------------
    | Provedor
    |--------------------------------------------------------------------------
    |
    | simulado : regista o envio localmente (teste, sem chamadas externas)
    | http     : API REST genérica (endpoint + chave configurados abaixo)
    | twilio   : API da Twilio (aceita credenciais de teste)
    |
    */

    'provider' => env('SMS_PROVIDER', 'simulado'),

    /*
    | Remetente apresentado ao destinatário.
    */

    'from' => env('SMS_FROM', 'MULTITEL'),

    /*
    |--------------------------------------------------------------------------
    | API REST genérica
    |--------------------------------------------------------------------------
    |
    | Cada gateway usa nomes de campos e esquemas de autenticação próprios.
    | Tudo o que varia está aqui, para ligar um gateway novo sem tocar no
    | código.
    |
    */

    'endpoint' => env('SMS_ENDPOINT'),

    'http_method' => env('SMS_HTTP_METHOD', 'POST'),

    /*
    | Autenticação: bearer | basic | header | query | none
    */
    'auth' => env('SMS_AUTH', 'bearer'),

    'api_key' => env('SMS_API_KEY'),

    'api_secret' => env('SMS_API_SECRET'),

    'auth_header' => env('SMS_AUTH_HEADER', 'Authorization'),

    'auth_scheme' => env('SMS_AUTH_SCHEME', 'Bearer'),

    'auth_query_param' => env('SMS_AUTH_QUERY_PARAM', 'api_key'),

    /*
    | Nomes dos campos no corpo do pedido.
    */
    'campo_destino' => env('SMS_CAMPO_DESTINO', 'to'),

    'campo_mensagem' => env('SMS_CAMPO_MENSAGEM', 'text'),

    'campo_remetente' => env('SMS_CAMPO_REMETENTE', 'from'),

    /*
    | Formato do número enviado ao gateway:
    | internacional (+244923000111) | sem_mais (244923000111) | nacional (923000111)
    */
    'formato_numero' => env('SMS_FORMATO_NUMERO', 'internacional'),

    /*
    | Campos fixos adicionais, em JSON (ex.: {"sender":"MULTITEL"}).
    */
    'campos_extra' => env('SMS_CAMPOS_EXTRA'),

    /*
    | Campo da resposta que traz o identificador do envio.
    */
    'campo_referencia' => env('SMS_CAMPO_REFERENCIA', 'id'),

    'timeout' => (int) env('SMS_TIMEOUT', 10),

    /*
    |--------------------------------------------------------------------------
    | Twilio
    |--------------------------------------------------------------------------
    */

    'twilio' => [
        'sid' => env('TWILIO_ACCOUNT_SID'),
        'token' => env('TWILIO_AUTH_TOKEN'),
        'from' => env('TWILIO_FROM'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Prefixo telefónico
    |--------------------------------------------------------------------------
    |
    | Angola. Os números são guardados sem prefixo (9 dígitos) e normalizados
    | para o formato internacional antes do envio.
    |
    */

    'country_code' => env('SMS_COUNTRY_CODE', '+244'),

    /*
    |--------------------------------------------------------------------------
    | Modelos de mensagem
    |--------------------------------------------------------------------------
    |
    | {nome} é substituído pelo primeiro nome do destinatário.
    |
    */

    'messages' => [

        'candidatura_aprovada' => 'Parabéns, {nome}! A tua candidatura ao estágio MULTITEL foi aceite. Vais fazer parte do programa de estágios. Entraremos em contacto para os próximos passos.',

    ],

];
