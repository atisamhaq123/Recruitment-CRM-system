$ErrorActionPreference = 'Stop'
Set-Location -LiteralPath $PSScriptRoot
$phpCommand = Get-Command php -ErrorAction SilentlyContinue
if ($phpCommand) {
    $talentFlowPhp = $phpCommand.Source
} elseif (Test-Path -LiteralPath 'C:\wamp64\bin\php\php8.4.15\php.exe') {
    $talentFlowPhp = 'C:\wamp64\bin\php\php8.4.15\php.exe'
} else {
    throw 'PHP 8.3 or newer is required. Add your PHP installation to PATH.'
}
if (!(Test-Path -LiteralPath 'vendor\autoload.php')) {
    throw 'Install Composer dependencies first. See README.md.'
}
& $talentFlowPhp artisan serve --host=127.0.0.1 --port=8000 --no-reload
