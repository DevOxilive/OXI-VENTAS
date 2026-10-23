#Requires -Version 5.1
<#
.SYNOPSIS
    Autoriza el certificado publico de Super-Kay para el usuario actual de QZ Tray.

.DESCRIPTION
    Este instalador no genera certificados ni recibe llaves privadas. Copia solamente
    el certificado publico que firma las solicitudes del POS, lo agrega a la lista
    permitida de QZ Tray, configura la confianza necesaria para imprimir sin avisos
    y valida el entorno local de impresion.
#>
[CmdletBinding()]
param(
    [Parameter(Mandatory = $true)]
    [ValidateNotNullOrEmpty()]
    [string]$CertificatePath,

    [switch]$RestartQzTray
)

Set-StrictMode -Version Latest
$ErrorActionPreference = 'Stop'

function Get-QzTrayPath {
    [array]$candidates = @(
        (Join-Path $env:ProgramFiles 'QZ Tray\qz-tray-console.exe'),
        (Join-Path ${env:ProgramFiles(x86)} 'QZ Tray\qz-tray-console.exe')
    ) | Where-Object { $_ -and (Test-Path -LiteralPath $_ -PathType Leaf) }

    if ($candidates.Count -eq 0) {
        throw 'No se encontro QZ Tray. Instala QZ Tray 2.2.6 o posterior antes de ejecutar este instalador.'
    }

    return $candidates[0]
}

function Test-QzTrayStartup {
    return @(
        Get-CimInstance Win32_StartupCommand -ErrorAction SilentlyContinue | Where-Object {
            $_.Name -match 'QZ Tray' -or $_.Command -match 'qz-tray'
        }
    ).Count -gt 0
}

function Add-QzTrayStartupShortcut {
    param(
        [Parameter(Mandatory = $true)]
        [string]$QzTrayExecutable
    )

    $startupDirectory = [Environment]::GetFolderPath('Startup')
    $shortcutPath = Join-Path $startupDirectory 'Super Kay - QZ Tray.lnk'
    $shell = New-Object -ComObject WScript.Shell
    $shortcut = $shell.CreateShortcut($shortcutPath)
    $shortcut.TargetPath = $QzTrayExecutable
    $shortcut.WorkingDirectory = Split-Path $QzTrayExecutable -Parent
    $shortcut.Save()

    return $shortcutPath
}

function Get-PrinterInventory {
    return @(
        Get-CimInstance Win32_Printer -ErrorAction Stop |
            Sort-Object Name |
            Select-Object Name, DriverName, PortName, WorkOffline
    )
}

if (-not (Test-Path -LiteralPath $CertificatePath -PathType Leaf)) {
    throw "No existe el certificado publico indicado: $CertificatePath"
}

$certificateContents = Get-Content -LiteralPath $CertificatePath -Raw
if ($certificateContents -notmatch '-----BEGIN CERTIFICATE-----' -or $certificateContents -match '-----BEGIN (?:RSA )?PRIVATE KEY-----') {
    throw 'El archivo debe contener solo un certificado publico PEM; las llaves privadas nunca se instalan en las cajas.'
}

$qzExecutable = Get-QzTrayPath
$qzDirectory = Split-Path $qzExecutable -Parent
$qzTrayExecutable = Join-Path $qzDirectory 'qz-tray.exe'
$destinationDirectory = Join-Path $env:LOCALAPPDATA 'Super-Kay\QZ Tray'
$destinationPath = Join-Path $destinationDirectory 'super-kay-qz-public.pem'
$installedHashPath = Join-Path $destinationDirectory 'super-kay-qz-public.sha256'
$reportPath = Join-Path $destinationDirectory 'diagnostico-impresoras.txt'
New-Item -ItemType Directory -Path $destinationDirectory -Force | Out-Null

$spooler = Get-Service -Name Spooler -ErrorAction Stop
if ($spooler.Status -ne 'Running') {
    Write-Host 'Iniciando el servicio de impresion de Windows...' -ForegroundColor Yellow
    Start-Service -Name Spooler
    $spooler.WaitForStatus('Running', [TimeSpan]::FromSeconds(15))
}

if ($spooler.StartType -ne 'Automatic') {
    try {
        Set-Service -Name Spooler -StartupType Automatic
    } catch {
        Write-Warning 'No fue posible configurar el servicio de impresion como automatico. Ejecuta el instalador como administrador si la advertencia se repite.'
    }
}

if (-not (Test-QzTrayStartup)) {
    $startupShortcut = Add-QzTrayStartupShortcut -QzTrayExecutable $qzTrayExecutable
    Write-Host "Se agrego QZ Tray al inicio de Windows: $startupShortcut" -ForegroundColor Green
}

$sourceHash = (Get-FileHash -LiteralPath $CertificatePath -Algorithm SHA256).Hash
$destinationHash = if (Test-Path -LiteralPath $destinationPath) {
    (Get-FileHash -LiteralPath $destinationPath -Algorithm SHA256).Hash
} else {
    $null
}

if ($sourceHash -ne $destinationHash) {
    Copy-Item -LiteralPath $CertificatePath -Destination $destinationPath -Force
}

$allowedPath = Join-Path $env:APPDATA 'qz\allowed.dat'
$knownCertificateHash = if (Test-Path -LiteralPath $installedHashPath) {
    (Get-Content -LiteralPath $installedHashPath -Raw).Trim()
} else {
    $null
}
$hasSuperKayEntry = (Test-Path -LiteralPath $allowedPath) -and (
    (Get-Content -LiteralPath $allowedPath -Raw) -match "(?m)^[^`r`n]*`tOXI VENTAS QZ Tray`tOXI VENTAS`t"
)
$requiresAllow = ($knownCertificateHash -ne $sourceHash) -or -not $hasSuperKayEntry

if ($requiresAllow) {
    $LASTEXITCODE = 0
    $allowOutput = & $qzExecutable '--allow' $destinationPath 2>&1
    if ($LASTEXITCODE -ne 0) {
        throw "QZ Tray no pudo autorizar el certificado: $($allowOutput -join ' ')"
    }
}

Set-Content -LiteralPath $installedHashPath -Value $sourceHash -NoNewline

$currentOptions = [Environment]::GetEnvironmentVariable('QZ_OPTS', 'User')
$withoutPreviousCertificate = [regex]::Replace(
    [string]$currentOptions,
    '(?<!\S)-D(?:trustedRootCert|authcert\.override)=(?:"[^"]*"|\S+)',
    ''
).Trim()
$trustedRootOption = '-DtrustedRootCert="'+$destinationPath+'"'
$newOptions = ((@($withoutPreviousCertificate, $trustedRootOption) | Where-Object { $_ }) -join ' ').Trim()
[Environment]::SetEnvironmentVariable('QZ_OPTS', $newOptions, 'User')
$env:QZ_OPTS = $newOptions

if ($RestartQzTray) {
    $qzProcesses = @(
        Get-CimInstance Win32_Process -ErrorAction SilentlyContinue | Where-Object {
            $_.Name -in @('qz-tray.exe', 'qz-tray-console.exe', 'java.exe', 'javaw.exe') -and (
                $_.ExecutablePath -like "$qzDirectory\\*" -or $_.CommandLine -like '*qz-tray.jar*'
            )
        }
    )

    if ($qzProcesses.Count -gt 0) {
        Stop-Process -Id $qzProcesses.ProcessId -Force
        Start-Sleep -Seconds 1
    }

    Start-Process -FilePath $qzTrayExecutable

    $qzListening = $false
    for ($attempt = 0; $attempt -lt 30; $attempt++) {
        Start-Sleep -Seconds 1
        $qzListening = [bool](
            Get-NetTCPConnection -LocalPort 8181 -State Listen -ErrorAction SilentlyContinue
        )

        if ($qzListening) {
            break
        }
    }

    if (-not $qzListening) {
        Write-Warning 'QZ Tray no abrio el puerto 8181 durante la espera. Puede seguir iniciando; revisa su icono antes de probar.'
    }
}

$printerInventory = Get-PrinterInventory
$reportLines = @(
    'SUPER KAY - DIAGNOSTICO DE IMPRESORAS',
    "Fecha: $(Get-Date -Format 'yyyy-MM-dd HH:mm:ss')",
    "Usuario: $env:USERNAME",
    "Equipo: $env:COMPUTERNAME",
    "Servicio de impresion: $((Get-Service -Name Spooler).Status)",
    "QZ Tray en inicio de Windows: $(Test-QzTrayStartup)",
    '',
    'Colas detectadas:'
)

if ($printerInventory.Count -eq 0) {
    $reportLines += '- No se encontraron impresoras instaladas.'
} else {
    foreach ($printer in $printerInventory) {
        $availability = if ($printer.WorkOffline) { 'Sin conexion' } else { 'Disponible' }
        $reportLines += "- $($printer.Name) | Driver: $($printer.DriverName) | Puerto: $($printer.PortName) | $availability"
    }
}

$reportLines | Set-Content -LiteralPath $reportPath -Encoding UTF8

Write-Host 'QZ Tray quedo autorizado para este usuario de Windows.' -ForegroundColor Green
Write-Host "Certificado publico: $destinationPath"
Write-Host "Huella SHA-256: $sourceHash"
Write-Host ''
Write-Host 'Impresoras encontradas por Windows:' -ForegroundColor Cyan
if ($printerInventory.Count -eq 0) {
    Write-Warning 'No se encontraron impresoras. Instala los drivers y vuelve a ejecutar este archivo.'
} else {
    $printerInventory | Format-Table Name, DriverName, PortName, WorkOffline -AutoSize
}
Write-Host "Diagnostico guardado en: $reportPath"
Write-Host 'Abre Super-Kay > Impresoras > Configurar impresoras, asigna ambas colas y realiza las pruebas.' -ForegroundColor Green
