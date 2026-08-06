@echo off
setlocal EnableExtensions

rem Service jalan di BACKGROUND. Jendela CMD ini tertutup otomatis.

set "PORT=8008"
set "VITE_PORT=5173"
set "ROOT=%~dp0"
set "APP_DIR=%ROOT%webapp"
set "LOG_DIR=%APP_DIR%\storage\logs"
set "PID_FILE=%LOG_DIR%\dev-server.pid"
set "LOG_FILE=%LOG_DIR%\dev-watch.log"
set "PS1=%APP_DIR%\scripts\run-dev-background.ps1"

if not exist "%APP_DIR%\artisan" (
    echo [ERROR] Folder webapp / artisan tidak ditemukan:
    echo   %APP_DIR%
    pause
    exit /b 1
)

where php >nul 2>&1
if errorlevel 1 (
    echo [ERROR] PHP tidak ditemukan di PATH.
    pause
    exit /b 1
)

where node >nul 2>&1
if errorlevel 1 (
    echo [ERROR] Node.js tidak ditemukan di PATH.
    pause
    exit /b 1
)

if not exist "%APP_DIR%\node_modules\nodemon\" (
    echo [INFO] Menginstall dependency npm...
    pushd "%APP_DIR%"
    call npm install
    if errorlevel 1 (
        echo [ERROR] npm install gagal.
        popd
        pause
        exit /b 1
    )
    popd
)

if not exist "%LOG_DIR%" mkdir "%LOG_DIR%"

echo Menghentikan proses lama...
powershell -NoProfile -ExecutionPolicy Bypass -Command ^
  "$ports=@(%PORT%,%VITE_PORT%); foreach($p in $ports){ Get-NetTCPConnection -LocalPort $p -State Listen -ErrorAction SilentlyContinue | ForEach-Object { Stop-Process -Id $_.OwningProcess -Force -ErrorAction SilentlyContinue } }; if (Test-Path -LiteralPath '%PID_FILE%') { $old=Get-Content -LiteralPath '%PID_FILE%' -ErrorAction SilentlyContinue; if ($old) { Stop-Process -Id ([int]$old) -Force -ErrorAction SilentlyContinue }; Remove-Item -LiteralPath '%PID_FILE%' -Force -ErrorAction SilentlyContinue }"

ping 127.0.0.1 -n 2 >nul

echo Menjalankan service di background...
powershell -NoProfile -ExecutionPolicy Bypass -File "%PS1%" -AppDir "%APP_DIR%" -LogFile "%LOG_FILE%" -PidFile "%PID_FILE%"
if errorlevel 1 (
    echo [ERROR] Gagal start background service.
    pause
    exit /b 1
)

echo.
echo ========================================
echo  Service berjalan di BACKGROUND
echo  Local : http://localhost:%PORT%
echo  LAN   : http://192.168.100.249:%PORT%
echo  Log   : %LOG_FILE%
echo  Stop  : stop-server.bat
echo ========================================
echo.
echo Jendela ini akan tertutup...
ping 127.0.0.1 -n 3 >nul
endlocal
exit
