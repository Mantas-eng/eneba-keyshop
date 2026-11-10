$php = Get-Command php -ErrorAction SilentlyContinue
if ($php) {
    & $php.Source -S 127.0.0.1:8080 -t "$PSScriptRoot/public"
} else {
    $dir = Join-Path $env:LOCALAPPDATA 'Temp/eneba-php'
    if (!(Test-Path "$dir/php.exe")) { throw 'Install PHP 8.2+ with pdo_sqlite, or use Docker. See README.md.' }
    & "$dir/php.exe" -d "extension_dir=$dir/ext" -d extension=pdo_sqlite -d "session.save_path=$env:TEMP" -S 127.0.0.1:8080 -t "$PSScriptRoot/public"
}
