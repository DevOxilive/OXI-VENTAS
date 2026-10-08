<?php

namespace App\Http\Controllers;

use Inertia\Inertia;
use Inertia\Response as InertiaResponse;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use ZipArchive;

class QzTrayInstallerController extends Controller
{
    public function index(): InertiaResponse
    {
        return Inertia::render('Printers/QzTraySetup');
    }

    public function download(): BinaryFileResponse
    {
        $certificatePath = config('qz.certificate_path');
        $scriptPath = base_path('scripts/install-qz-tray-trust.ps1');

        abort_unless(is_string($certificatePath) && is_file($certificatePath), 404, 'El certificado público de QZ no está configurado.');
        abort_unless(is_file($scriptPath), 500, 'No se encontró el instalador de QZ Tray.');
        abort_unless(class_exists(ZipArchive::class), 500, 'El servidor no tiene disponible la extensión ZIP.');

        $temporaryPath = tempnam(sys_get_temp_dir(), 'super-kay-qz-');
        abort_unless($temporaryPath !== false, 500, 'No se pudo preparar el instalador de QZ Tray.');

        $zip = new ZipArchive();
        $opened = $zip->open($temporaryPath, ZipArchive::OVERWRITE);

        if ($opened !== true) {
            @unlink($temporaryPath);
            abort(500, 'No se pudo crear el archivo de instalación de QZ Tray.');
        }

        $zip->addFile($certificatePath, 'qz-public.pem');
        $zip->addFile($scriptPath, 'install-qz-tray-trust.ps1');
        $zip->addFromString('INSTALAR-QZ.bat', $this->launcherContents());
        $zip->addFromString('LEEME.txt', $this->readmeContents());
        $zip->close();

        return response()
            ->download($temporaryPath, 'super-kay-qz-tray-installer.zip', [
                'Content-Type' => 'application/zip',
                'Cache-Control' => 'no-store, private',
            ])
            ->deleteFileAfterSend(true);
    }

    private function launcherContents(): string
    {
        return <<<BAT
@echo off
setlocal
title Super-Kay - Configurar QZ Tray

echo.
echo Este instalador autorizara QZ Tray para Super-Kay en este usuario de Windows.
echo.

set "POWERSHELL_EXE=%SystemRoot%\System32\WindowsPowerShell\\v1.0\powershell.exe"
if not exist "%POWERSHELL_EXE%" (
    echo No se encontro Windows PowerShell en esta computadora.
    pause
    exit /b 1
)

"%POWERSHELL_EXE%" -NoProfile -ExecutionPolicy Bypass -File "%~dp0install-qz-tray-trust.ps1" -CertificatePath "%~dp0qz-public.pem" -RestartQzTray
if errorlevel 1 (
    echo.
    echo No se pudo configurar QZ Tray. Lee el mensaje anterior y vuelve a intentarlo.
    pause
    exit /b 1
)

echo.
echo QZ Tray quedo listo. Abre Super-Kay e imprime un ticket de prueba.
pause
BAT;
    }

    private function readmeContents(): string
    {
        return <<<TXT
1. Instala QZ Tray 2.2.6 o posterior.
2. Descomprime este archivo ZIP completo.
3. Haz doble clic en INSTALAR-QZ.bat con el mismo usuario de Windows que usa Super-Kay.
4. Abre Super-Kay e imprime un ticket de prueba.
TXT;
    }
}
