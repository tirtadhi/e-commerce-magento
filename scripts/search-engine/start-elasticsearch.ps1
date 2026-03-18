param(
    [string]$ContainerName = "magento-es7",
    [string]$Image = "docker.elastic.co/elasticsearch/elasticsearch:7.17.24",
    [int]$Port = 9200,
    [int]$TimeoutSeconds = 120
)

$ErrorActionPreference = "Stop"

function Test-DockerReady {
    docker info | Out-Null
    return $LASTEXITCODE -eq 0
}

function Ensure-DockerReady {
    if (Test-DockerReady) {
        return
    }

    $dockerDesktop = "C:\Program Files\Docker\Docker\Docker Desktop.exe"
    if (Test-Path $dockerDesktop) {
        Write-Host "Starting Docker Desktop..."
        Start-Process $dockerDesktop | Out-Null
    } else {
        throw "Docker daemon is not ready and Docker Desktop was not found at '$dockerDesktop'."
    }

    $deadline = (Get-Date).AddSeconds($TimeoutSeconds)
    while ((Get-Date) -lt $deadline) {
        Start-Sleep -Seconds 2
        if (Test-DockerReady) {
            return
        }
    }

    throw "Docker daemon did not become ready within $TimeoutSeconds seconds."
}

function Get-ContainerState {
    param([string]$Name)

    $state = docker ps -a --filter "name=^/$Name$" --format "{{.State}}"
    if ($LASTEXITCODE -ne 0 -or [string]::IsNullOrWhiteSpace($state)) {
        return $null
    }

    return $state.Trim()
}

function Wait-ForElasticsearch {
    param(
        [int]$Port,
        [int]$TimeoutSeconds
    )

    $deadline = (Get-Date).AddSeconds($TimeoutSeconds)
    while ((Get-Date) -lt $deadline) {
        try {
            $response = curl.exe -s "http://localhost:$Port"
            if ($LASTEXITCODE -eq 0 -and $response -match '"cluster_name"') {
                return
            }
        } catch {
            # Keep polling until timeout.
        }
        Start-Sleep -Seconds 2
    }

    throw "Elasticsearch endpoint http://localhost:$Port was not ready within $TimeoutSeconds seconds."
}

Ensure-DockerReady

$state = Get-ContainerState -Name $ContainerName
if ($state -eq "running") {
    Write-Host "Container '$ContainerName' is already running."
} elseif ($state) {
    Write-Host "Starting existing container '$ContainerName'..."
    docker start $ContainerName | Out-Null
} else {
    Write-Host "Creating container '$ContainerName' from image '$Image'..."
    docker run -d --name $ContainerName -p ${Port}:9200 -e "discovery.type=single-node" -e "xpack.security.enabled=false" -e "ES_JAVA_OPTS=-Xms512m -Xmx512m" $Image | Out-Null
}

Write-Host "Waiting for Elasticsearch on http://localhost:$Port ..."
Wait-ForElasticsearch -Port $Port -TimeoutSeconds $TimeoutSeconds

Write-Host "Elasticsearch is ready."
docker ps --filter "name=^/$ContainerName$" --format "{{.Names}}|{{.Status}}|{{.Ports}}"
