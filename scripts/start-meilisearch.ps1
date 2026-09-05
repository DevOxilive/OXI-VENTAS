param(
    [string] $BinaryPath = '',
    [string] $DataPath = 'C:\laragon\data\meilisearch-oxi-ventas',
    [string] $LogDirectory = 'C:\laragon\log',
    [string] $HttpAddress = '127.0.0.1:7700',
    [switch] $Foreground
)

$ErrorActionPreference = 'Stop'
$healthUrl = "http://$HttpAddress/health"

function Test-MeilisearchHealth {
    try {
        $response = Invoke-RestMethod -Uri $healthUrl -TimeoutSec 1

        return $response.status -eq 'available'
    } catch {
        return $false
    }
}

if (Test-MeilisearchHealth) {
    Write-Output "Meilisearch ya esta disponible en $healthUrl."
    exit 0
}

if ([string]::IsNullOrWhiteSpace($BinaryPath)) {
    $BinaryPath = Get-ChildItem -LiteralPath 'C:\laragon\bin\meilisearch' -Filter 'meilisearch-v*.exe' -File |
        Sort-Object { [version] ($_.BaseName -replace '^meilisearch-v', '') } -Descending |
        Select-Object -First 1 -ExpandProperty FullName
}

if (-not $BinaryPath -or -not (Test-Path -LiteralPath $BinaryPath -PathType Leaf)) {
    throw 'No se encontro el ejecutable Community de Meilisearch en C:\laragon\bin\meilisearch.'
}

New-Item -ItemType Directory -Path $DataPath -Force | Out-Null
New-Item -ItemType Directory -Path $LogDirectory -Force | Out-Null

$standardLog = Join-Path $LogDirectory 'meilisearch-oxi-ventas.log'
$errorLog = Join-Path $LogDirectory 'meilisearch-oxi-ventas-error.log'
$arguments = @(
    '--http-addr', $HttpAddress,
    '--db-path', $DataPath,
    '--env', 'development',
    '--no-analytics'
)

if ($Foreground) {
    Write-Output "Meilisearch se ejecutara en primer plano desde $healthUrl."
    & $BinaryPath @arguments

    exit $LASTEXITCODE
}

Start-Process -FilePath $BinaryPath `
    -ArgumentList $arguments `
    -WorkingDirectory (Split-Path -Parent $BinaryPath) `
    -WindowStyle Hidden `
    -RedirectStandardOutput $standardLog `
    -RedirectStandardError $errorLog

for ($attempt = 1; $attempt -le 20; $attempt++) {
    Start-Sleep -Milliseconds 500

    if (Test-MeilisearchHealth) {
        Write-Output "Meilisearch inicio correctamente en $healthUrl."
        exit 0
    }
}

throw "Meilisearch no respondio despues de 10 segundos. Revisa $errorLog."
