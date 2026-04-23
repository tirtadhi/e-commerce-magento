param(
    [string]$DumpFile = "database/magento2-laragon.sql",
    [string]$EnvFile = "docker/.env.docker"
)

$ErrorActionPreference = "Stop"

if (!(Test-Path $DumpFile)) {
    throw "Dump file not found: $DumpFile"
}

if (!(Test-Path $EnvFile)) {
    throw "Env file not found: $EnvFile. Copy docker/.env.docker.example first."
}

Write-Host "Ensuring docker services are up..."
docker compose --env-file $EnvFile up -d mysql
if ($LASTEXITCODE -ne 0) {
    throw "Failed to start mysql service"
}

Write-Host "Creating database if not exists..."
docker compose --env-file $EnvFile exec -T mysql sh -c "mysql -uroot -p\"$MYSQL_ROOT_PASSWORD\" -e 'CREATE DATABASE IF NOT EXISTS magento2;'"
if ($LASTEXITCODE -ne 0) {
    throw "Failed creating database"
}

Write-Host "Importing dump file $DumpFile into mysql container..."
Get-Content $DumpFile | docker compose --env-file $EnvFile exec -T mysql sh -c "mysql -u\"$MYSQL_USER\" -p\"$MYSQL_PASSWORD\" magento2"
if ($LASTEXITCODE -ne 0) {
    throw "Import failed"
}

Write-Host "Import complete."
