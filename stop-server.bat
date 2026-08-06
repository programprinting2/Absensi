@echo off
setlocal EnableExtensions

set "PORT=8008"
set "VITE_PORT=5173"
set "APP_DIR=%~dp0webapp"
set "PID_FILE=%APP_DIR%\storage\logs\dev-server.pid"

echo Menghentikan service background...

powershell -NoProfile -ExecutionPolicy Bypass -Command ^
  "if (Test-Path -LiteralPath '%PID_FILE%') { $old=Get-Content -LiteralPath '%PID_FILE%' -ErrorAction SilentlyContinue; if ($old) { Stop-Process -Id ([int]$old) -Force -ErrorAction SilentlyContinue; Write-Host ('Stopped PID ' + $old) }; Remove-Item -LiteralPath '%PID_FILE%' -Force -ErrorAction SilentlyContinue }; $ports=@(%PORT%,%VITE_PORT%); foreach($p in $ports){ Get-NetTCPConnection -LocalPort $p -State Listen -ErrorAction SilentlyContinue | ForEach-Object { Stop-Process -Id $_.OwningProcess -Force -ErrorAction SilentlyContinue; Write-Host ('Stopped port ' + $p + ' PID ' + $_.OwningProcess) } }"

echo Selesai.
ping 127.0.0.1 -n 2 >nul
endlocal
exit
