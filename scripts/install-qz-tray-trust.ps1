#Requires -Version 5.1
<#
.SYNOPSIS
    Autoriza el certificado publico de Super-Kay para el usuario actual de QZ Tray.

.DESCRIPTION
    Este instalador no genera certificados ni recibe llaves privadas. Copia solamente
    el certificado publico que firma las solicitudes del POS, lo agrega a la lista
    permitida de QZ Tray y configura la confianza necesaria para imprimir sin avisos.
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

if (-not (Test-Path -LiteralPath $CertificatePath -PathType Leaf)) {
    throw "No existe el certificado publico indicado: $CertificatePath"
}

$certificateContents = Get-Content -LiteralPath $CertificatePath -Raw
if ($certificateContents -notmatch '-----BEGIN CERTIFICATE-----' -or $certificateContents -match '-----BEGIN (?:RSA )?PRIVATE KEY-----') {
    throw 'El archivo debe contener solo un certificado publico PEM; las llaves privadas nunca se instalan en las cajas.'
}

$qzExecutable = Get-QzTrayPath
$destinationDirectory = Join-Path $env:LOCALAPPDATA 'Super-Kay\QZ Tray'
$destinationPath = Join-Path $destinationDirectory 'super-kay-qz-public.pem'
$installedHashPath = Join-Path $destinationDirectory 'super-kay-qz-public.sha256'
New-Item -ItemType Directory -Path $destinationDirectory -Force | Out-Null

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

if ($RestartQzTray) {
    $qzDirectory = Split-Path $qzExecutable -Parent
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

    Start-Process -FilePath (Join-Path $qzDirectory 'qz-tray.exe')
}

Write-Host 'QZ Tray quedo autorizado para este usuario de Windows.' -ForegroundColor Green
Write-Host "Certificado publico: $destinationPath"
Write-Host "Huella SHA-256: $sourceHash"
Write-Host 'Abre de nuevo el punto de venta y realiza una impresion de prueba.'
