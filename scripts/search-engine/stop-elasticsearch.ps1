param(
    [string]$ContainerName = "magento-es7",
    [switch]$Remove
)

$ErrorActionPreference = "Stop"

$state = docker ps -a --filter "name=^/$ContainerName$" --format "{{.State}}"
if ($LASTEXITCODE -ne 0 -or [string]::IsNullOrWhiteSpace($state)) {
    Write-Host "Container '$ContainerName' was not found."
    exit 0
}

$state = $state.Trim()
if ($state -eq "running") {
    Write-Host "Stopping container '$ContainerName'..."
    docker stop $ContainerName | Out-Null
} else {
    Write-Host "Container '$ContainerName' is already stopped."
}

if ($Remove.IsPresent) {
    Write-Host "Removing container '$ContainerName'..."
    docker rm $ContainerName | Out-Null
}

Write-Host "Done."
