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
        $zip->addFromString('CONFIGURAR-IMPRESORAS.bat', $this->launcherContents());
        $zip->addFromString('LEEME.txt', $this->readmeContents());
        $zip->close();

        return response()
            ->download($temporaryPath, 'super-kay-configurar-impresoras.zip', [
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
title Super-Kay - Configurar impresoras

echo.
echo Este instalador preparara QZ Tray y revisara las impresoras de esta computadora.
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
    echo No se pudo preparar la computadora. Lee el mensaje anterior y vuelve a intentarlo.
    pause
    exit /b 1
)

echo.
echo La preparacion termino. Abre Configurar impresoras en Super-Kay,
echo asigna las dos colas y realiza ambas pruebas fisicas.
pause
BAT;
    }

    private function readmeContents(): string
    {
        return <<<TXT
1. Instala los drivers de la impresora de tickets y de la impresora de etiquetas.
2. Instala QZ Tray 2.2.6 o posterior.
3. Descomprime este archivo ZIP completo.
4. Haz doble clic en CONFIGURAR-IMPRESORAS.bat con el mismo usuario de Windows que usa Super-Kay.
5. Revisa las impresoras encontradas y cualquier advertencia del instalador.
6. Abre Super-Kay > Impresoras > Configurar impresoras.
7. Asigna una cola para tickets, otra para etiquetas y realiza ambas pruebas fisicas.
TXT;
    }
}
