param(
    [string]$DatabaseName = "magento2",
    [string]$OutputFile = "database/magento2-laragon.sql",
    [string]$MySqlBinPath = "C:/laragon/bin/mysql/mysql-8.0.30-winx64/bin"
)

$ErrorActionPreference = "Stop"

$mysqldump = Join-Path $MySqlBinPath "mysqldump.exe"
if (!(Test-Path $mysqldump)) {
    throw "mysqldump not found at: $mysqldump"
}

New-Item -ItemType Directory -Path (Split-Path $OutputFile -Parent) -Force | Out-Null

Write-Host "Exporting database '$DatabaseName' to '$OutputFile'..."
& $mysqldump --host=127.0.0.1 --port=3306 --user=root --password= --single-transaction --routines --triggers --default-character-set=utf8mb4 --set-gtid-purged=OFF --no-tablespaces $DatabaseName > $OutputFile

if ($LASTEXITCODE -ne 0) {
    throw "mysqldump failed with exit code $LASTEXITCODE"
}

$file = Get-Item $OutputFile
Write-Host "Done. Output size: $([Math]::Round($file.Length / 1MB, 2)) MB"
