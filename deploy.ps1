# ============================================================
# SGIM - SCRIPT DE DEPLOY DEFINITIVO
# Uso: .\deploy.ps1 -Versao "1.1.69" -Mensagem "descrição"
# ============================================================
param(
    [Parameter(Mandatory=$true)]
    [string]$Versao,
    [string]$Mensagem = "Atualização de sistema"
)

$ErrorActionPreference = "Stop"
$Root = $PSScriptRoot

Write-Host ""
Write-Host "=====================================================" -ForegroundColor Cyan
Write-Host "  SGIM DEPLOY v$Versao" -ForegroundColor Cyan
Write-Host "=====================================================" -ForegroundColor Cyan
Write-Host ""

# --- PASSO 1: Atualizar versão no header.php ---
Write-Host "[1/6] Atualizando versao no header.php..." -ForegroundColor Yellow
$headerFile = "$Root\SGIM-VENDAS\source_cliente\includes\header.php"
$headerContent = Get-Content $headerFile -Raw
$headerContent = $headerContent -replace "GLOBAL HEADER v\d+\.\d+\.\d+", "GLOBAL HEADER v$Versao"
Set-Content $headerFile $headerContent -NoNewline
Write-Host "      header.php -> v$Versao" -ForegroundColor Green

# --- PASSO 2: Atualizar versão no schema.sql ---
Write-Host "[2/6] Atualizando versao no schema.sql..." -ForegroundColor Yellow
$schemaFile = "$Root\SGIM-VENDAS\source_cliente\database\schema.sql"
$schemaContent = Get-Content $schemaFile -Raw
$schemaContent = $schemaContent -replace "VALUES \('versao_sistema', '\d+\.\d+\.\d+'\)", "VALUES ('versao_sistema', '$Versao')"
Set-Content $schemaFile $schemaContent -NoNewline
Write-Host "      schema.sql -> versao_sistema = $Versao" -ForegroundColor Green

# --- PASSO 3: Atualizar versão no setup.php ---
Write-Host "[3/6] Atualizando versao no setup.php..." -ForegroundColor Yellow
$setupFile = "$Root\SGIM-VENDAS\source_cliente\setup.php"
$setupContent = Get-Content $setupFile -Raw
$setupContent = $setupContent -replace "\\\$versao_instalada = '\d+\.\d+\.\d+'", "`$versao_instalada = '$Versao'"
Set-Content $setupFile $setupContent -NoNewline
Write-Host "      setup.php -> versao_instalada = $Versao" -ForegroundColor Green

# --- PASSO 4: Gerar ZIP do pacote OTA (para clientes existentes) ---
Write-Host "[4/6] Gerando pacote OTA: SGIM-CLIENTE-v$Versao.zip..." -ForegroundColor Yellow
$packagesDir = "$Root\SGIM-VENDAS\api\update\packages"
if (-not (Test-Path $packagesDir)) { New-Item -ItemType Directory -Path $packagesDir | Out-Null }
$otaZip = "$packagesDir\SGIM-CLIENTE-v$Versao.zip"
Compress-Archive -Path "$Root\SGIM-VENDAS\source_cliente\*" -DestinationPath $otaZip -Force
$otaSize = [math]::Round((Get-Item $otaZip).Length / 1024)
Write-Host "      OTA ZIP -> $otaZip ($otaSize KB)" -ForegroundColor Green

# --- PASSO 5: Atualizar latest.json ---
Write-Host "[5/6] Atualizando latest.json..." -ForegroundColor Yellow
$manifest = @{
    version      = $Versao
    url          = "https://escolateologicaeloha.com.br/api/update/packages/SGIM-CLIENTE-v$Versao.zip"
    sha256       = (Get-FileHash $otaZip -Algorithm SHA256).Hash.ToLower()
    notes        = "v${Versao}: $Mensagem"
    date         = (Get-Date -Format "yyyy-MM-dd")
}
$manifest | ConvertTo-Json -Depth 3 | Set-Content "$Root\SGIM-VENDAS\api\update\latest.json" -Encoding UTF8
Write-Host "      latest.json -> version=$Versao | sha256=$($manifest.sha256.Substring(0,16))..." -ForegroundColor Green

# --- PASSO 5b: Gerar sgim_master.zip (para novos clientes) ---
Write-Host "      Gerando sgim_master.zip (instalador comercial)..." -ForegroundColor Yellow
$downloadsDir = "$Root\SGIM-VENDAS\downloads"
if (-not (Test-Path $downloadsDir)) { New-Item -ItemType Directory -Path $downloadsDir | Out-Null }
Compress-Archive -Path "$Root\SGIM-VENDAS\source_cliente\*" -DestinationPath "$downloadsDir\sgim_master.zip" -Force
$masterSize = [math]::Round((Get-Item "$downloadsDir\sgim_master.zip").Length / 1024)
Write-Host "      sgim_master.zip -> $downloadsDir\sgim_master.zip ($masterSize KB)" -ForegroundColor Green

# --- PASSO 6: Sincronizar SGIM-CLIENTE e fazer Push ---
Write-Host "[6/6] Sincronizando SGIM-CLIENTE e fazendo Git Push..." -ForegroundColor Yellow
Copy-Item -Path "$Root\SGIM-VENDAS\source_cliente\*" -Destination "$Root\SGIM-CLIENTE\" -Recurse -Force

Set-Location $Root
git add .
git commit -m "deploy(v$Versao): $Mensagem [auto-deploy]"
git push origin main

Write-Host ""
Write-Host "=====================================================" -ForegroundColor Cyan
Write-Host "  DEPLOY v$Versao CONCLUIDO COM SUCESSO!" -ForegroundColor Green
Write-Host "=====================================================" -ForegroundColor Cyan
Write-Host ""
Write-Host "  Proximo passo: Faca o Pull no cPanel do Master" -ForegroundColor White
Write-Host "  Clientes existentes receberao: v$Versao via OTA" -ForegroundColor White
Write-Host "  Novos clientes receberao:      v$Versao via sgim_master.zip" -ForegroundColor White
Write-Host ""
