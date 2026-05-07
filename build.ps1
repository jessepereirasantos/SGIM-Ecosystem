$ErrorActionPreference = 'Stop'

Write-Host "Iniciando empacotamento do SGIM-CLIENTE..." -ForegroundColor Cyan

$sourceDir = ".\SGIM-CLIENTE"
$zipPath = ".\SGIM-VENDAS\downloads\sgim_master.zip"

# Garantir que a pasta de destino do ZIP existe
$destZipDir = [System.IO.Path]::GetDirectoryName($zipPath)
if (!(Test-Path $destZipDir)) {
    New-Item -ItemType Directory -Path $destZipDir -Force
}

# Remover zips antigos
if (Test-Path $zipPath) {
    Write-Host "Removendo versão anterior do ZIP..." -ForegroundColor Yellow
    Remove-Item $zipPath -Force
}

Write-Host "Comprimindo a pasta $sourceDir..." -ForegroundColor Cyan

# Criar uma pasta temporária limpa para o build
$tempDir = New-Item -ItemType Directory -Path "$env:TEMP\sgim_build_$(Get-Random)" -Force
Copy-Item -Path "$sourceDir\*" -Destination $tempDir.FullName -Recurse -Force

# Remover arquivos que NÃO devem ir no pacote
$exclude_patterns = @("config\db_config.php", ".installed", "backups\*.sql", "debug_*.log")
foreach ($pattern in $exclude_patterns) {
    $files = Get-ChildItem -Path "$($tempDir.FullName)\$pattern" -ErrorAction SilentlyContinue
    if ($files) { Remove-Item $files -Force -Recurse }
}

# Compactar TUDO da raiz da pasta temporária para o ZIP
Add-Type -AssemblyName "System.IO.Compression.FileSystem"
[System.IO.Compression.ZipFile]::CreateFromDirectory($tempDir.FullName, $zipPath)

# Limpar pasta temporária
Remove-Item -Path $tempDir.FullName -Recurse -Force

Write-Host "Pacote criado com sucesso! Arquivo: $zipPath" -ForegroundColor Green
