<?php
require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$t = (new App\Models\User)->getTable();
echo "Tabela: $t" . PHP_EOL;
foreach (Illuminate\Support\Facades\DB::table($t)->get() as $r) {
    echo $r->email . ' | ' . $r->tipo_utilizador . ' | ativo=' . $r->ativo . PHP_EOL;
}
