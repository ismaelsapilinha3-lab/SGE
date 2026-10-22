# ============================================================
# SGE - Agendador do Laravel (Windows)
# ============================================================
#
# Executa "artisan schedule:run" a partir da pasta onde este
# script esta guardado, para funcionar em qualquer instalacao
# sem caminhos absolutos.
#
# Agendar no Task Scheduler do Windows para correr a cada minuto:
#
#   schtasks /create /tn "SGE Agendador" /tr "powershell -NoProfile -ExecutionPolicy Bypass -File C:\laragon\www\SGE\executar-agendador.ps1" /sc minute /mo 1 /ru SYSTEM
#
# ============================================================

$raiz = Split-Path -Parent $MyInvocation.MyCommand.Path

Set-Location $raiz

# Usar o php do PATH; se nao existir, tentar o do Laragon.
$php = (Get-Command php -ErrorAction SilentlyContinue).Source

if (-not $php) {

    $php = "C:\laragon\bin\php\php-8.3.33-Win32-vs16-x64\php.exe"

}

if (-not (Test-Path $php)) {

    Write-Error "PHP nao encontrado. Instala o PHP ou corrige o caminho neste script."

    exit 1

}

$log = Join-Path $raiz "storage\logs\scheduler.log"

& $php artisan schedule:run *>> $log
