<#
.SYNOPSIS
    Apaga e recria um banco do sistema: estrutura, seed inicial e (opcional)
    uma das massas de demonstracao.

.DESCRIPTION
    Faz, em ordem:
      1. DROP DATABASE <Banco> WITH (FORCE)  -- derruba conexoes abertas
      2. CREATE DATABASE <Banco>
      3. 0000_baseline_schema.sql
      4. 0001_seed_inicial.sql e 0005_sessao_e_arquivo.sql (sessoes e arquivos, branch neon)
      5. a massa escolhida em -Massa (0002, 0003 ou 0004)

    DESTRUTIVO: tudo o que existir em <Banco> se perde. Sem -Confirmar, o
    script so mostra o que faria e sai.

    Host, porta, usuario e senha vem do .env do projeto (DB_HOST, DB_PORT,
    DB_USER, DB_PASS). O psql e procurado no PATH e, se nao estiver, na
    instalacao padrao do PostgreSQL no Windows.

.EXAMPLE
    powershell -File sql/recriar_banco.ps1 -Banco reciclagem -Confirmar
    Recria "reciclagem" com a massa do piloto (01/08 a 07/11/2026).

.EXAMPLE
    powershell -File sql/recriar_banco.ps1 -Banco reciclagem -Massa nenhuma -Confirmar
    Recria vazio: so a estrutura e o seed inicial, como numa instalacao nova.
#>
param(
    [Parameter(Mandatory = $true)] [string] $Banco,
    [ValidateSet('piloto', 'demo', 'massa', 'nenhuma')] [string] $Massa = 'piloto',
    [switch] $Confirmar
)

$ErrorActionPreference = 'Stop'
$sqlDir  = $PSScriptRoot
$envFile = Join-Path (Split-Path $sqlDir -Parent) '.env'

if ($Banco -notmatch '^[a-z_][a-z0-9_]*$') {
    throw "Nome de banco invalido: '$Banco'. Use letras minusculas, digitos e _."
}
if ($Banco -in @('postgres', 'template0', 'template1')) {
    throw "Recuso recriar o banco de sistema '$Banco'."
}

# --- conexao, do .env -------------------------------------------------------
$cfg = @{ DB_HOST = '127.0.0.1'; DB_PORT = '5432'; DB_USER = 'postgres'; DB_PASS = ''; DB_NAME = '' }
if (Test-Path $envFile) {
    foreach ($linha in Get-Content $envFile) {
        if ($linha -match '^\s*(DB_[A-Z]+)\s*=\s*(.*)$') {
            $cfg[$Matches[1]] = $Matches[2].Trim().Trim('"').Trim("'")
        }
    }
}

# --- psql -------------------------------------------------------------------
$psql = (Get-Command psql -ErrorAction SilentlyContinue).Source
if (-not $psql) {
    $psql = Get-ChildItem 'C:\Program Files\PostgreSQL\*\bin\psql.exe' -ErrorAction SilentlyContinue |
            Sort-Object { [int]$_.Directory.Parent.Name } -Descending | Select-Object -First 1 -ExpandProperty FullName
}
if (-not $psql) { throw 'psql nao encontrado (nem no PATH, nem em C:\Program Files\PostgreSQL).' }

$arquivos = @('0000_baseline_schema.sql', '0001_seed_inicial.sql', '0005_sessao_e_arquivo.sql')
switch ($Massa) {
    'demo'   { $arquivos += '0002_seed_demo.sql' }
    'massa'  { $arquivos += '0003_seed_massa.sql' }
    'piloto' { $arquivos += '0004_seed_piloto.sql' }
}

Write-Host "Servidor: $($cfg.DB_USER)@$($cfg.DB_HOST):$($cfg.DB_PORT)"
Write-Host "Banco:    $Banco  (APAGADO e recriado)"
Write-Host "Arquivos: $($arquivos -join ', ')"
if ($Banco -eq $cfg.DB_NAME) {
    Write-Host "ATENCAO: '$Banco' e o banco que o sistema usa (DB_NAME do .env)." -ForegroundColor Yellow
}
if (-not $Confirmar) {
    Write-Host ''
    Write-Host 'Nada foi feito. Rode de novo com -Confirmar para executar.' -ForegroundColor Yellow
    exit 1
}

$env:PGPASSWORD      = $cfg.DB_PASS
$env:PGCLIENTENCODING = 'UTF8'
$conexao = @('-h', $cfg.DB_HOST, '-p', $cfg.DB_PORT, '-U', $cfg.DB_USER, '-X', '-q', '-v', 'ON_ERROR_STOP=1')

function Invoke-Psql([string[]] $argumentos) {
    # As NOTICEs do psql saem no stderr; no PowerShell 5.1, com 'Stop', elas
    # virariam excecao. O que decide sucesso e o codigo de saida.
    $anterior = $ErrorActionPreference
    $ErrorActionPreference = 'Continue'
    try { & $psql @conexao @argumentos 2>&1 | ForEach-Object { "$_" -replace '^psql:[^ ]+ (NOTA|NOTICE):\s+', '' } }
    finally { $ErrorActionPreference = $anterior }
    if ($LASTEXITCODE -ne 0) { throw "psql falhou (codigo $LASTEXITCODE): $($argumentos -join ' ')" }
}

try {
    Invoke-Psql @('-d', 'postgres', '-c', "DROP DATABASE IF EXISTS $Banco WITH (FORCE)", '-c', "CREATE DATABASE $Banco")
    foreach ($arq in $arquivos) {
        Write-Host "-> $arq"
        Invoke-Psql @('-d', $Banco, '-f', (Join-Path $sqlDir $arq))
    }
    Write-Host "Pronto: '$Banco' recriado." -ForegroundColor Green
}
finally {
    Remove-Item Env:PGPASSWORD -ErrorAction SilentlyContinue
}
