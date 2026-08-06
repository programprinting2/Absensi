param(
    [Parameter(Mandatory = $true)]
    [string]$AppDir,

    [string]$LogFile = '',
    [string]$PidFile = ''
)

$ErrorActionPreference = 'Stop'

if (-not (Test-Path -LiteralPath (Join-Path $AppDir 'artisan'))) {
    throw "artisan tidak ditemukan di: $AppDir"
}

$logDir = Join-Path $AppDir 'storage\logs'
if (-not (Test-Path -LiteralPath $logDir)) {
    New-Item -ItemType Directory -Path $logDir -Force | Out-Null
}

if ([string]::IsNullOrWhiteSpace($LogFile)) {
    $LogFile = Join-Path $logDir 'dev-watch.log'
}
if ([string]::IsNullOrWhiteSpace($PidFile)) {
    $PidFile = Join-Path $logDir 'dev-server.pid'
}

# Pakai cmd agar npm.cmd + redirect log tidak bentrok di Start-Process
$arg = "/c npm run dev:watch >> `"$LogFile`" 2>&1"

$proc = Start-Process `
    -FilePath 'cmd.exe' `
    -ArgumentList $arg `
    -WorkingDirectory $AppDir `
    -WindowStyle Hidden `
    -PassThru

Set-Content -LiteralPath $PidFile -Value $proc.Id -Encoding ascii
Write-Output "STARTED PID=$($proc.Id) LOG=$LogFile"
