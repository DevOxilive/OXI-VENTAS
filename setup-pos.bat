@echo off
setlocal

cd /d "%~dp0"
powershell -NoProfile -ExecutionPolicy Bypass -File "%~dp0scripts\setup-pos.ps1"

if errorlevel 1 (
    echo.
    echo Ocurrio un error durante la configuracion.
    pause
    exit /b 1
)

echo.
echo Configuracion terminada.
pause
