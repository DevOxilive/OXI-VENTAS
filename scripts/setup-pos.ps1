$ErrorActionPreference = "Stop"

function Run-Step {
    param(
        [string] $Title,
        [string] $Command,
        [string[]] $Arguments = @()
    )

    Write-Host ""
    Write-Host "==> $Title" -ForegroundColor Cyan

    & $Command @Arguments

    if ($LASTEXITCODE -ne 0) {
        throw "Fallo el paso: $Title"
    }
}

$root = Split-Path -Parent $PSScriptRoot
Set-Location $root

Write-Host "Preparando OXI-VENTAS en: $root" -ForegroundColor Green

Run-Step "Instalando dependencias PHP" "composer" @("install")
Run-Step "Instalando dependencias frontend" "npm" @("install")
Run-Step "Migrando base de datos" "php" @("artisan", "migrate", "--force")
Run-Step "Configurando QZ Tray para esta computadora" "php" @("artisan", "qz:setup")
Run-Step "Compilando frontend" "npm" @("run", "build")

Write-Host ""
Write-Host "Listo. Reinicia QZ Tray, recarga el punto de venta y prueba imprimir." -ForegroundColor Green
