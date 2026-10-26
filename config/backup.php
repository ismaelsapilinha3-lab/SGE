<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Backups automáticos
    |--------------------------------------------------------------------------
    |
    | O comando app:backup cria um arquivo tar.gz com a base de dados e os
    | ficheiros privados/públicos. A pasta de destino deve ficar fora de
    | storage/app para não ser incluída no próprio backup.
    |
    */

    'enabled' => (bool) env('BACKUP_ENABLED', false),

    'path' => env('BACKUP_PATH') ?: storage_path('backups'),

    'keep_days' => (int) env('BACKUP_KEEP_DAYS', 14),

    'mysqldump_binary' => env('DB_DUMP_BINARY', 'mysqldump'),

    'files' => [
        'private' => storage_path('app/private'),
        'public' => storage_path('app/public'),
    ],

];
