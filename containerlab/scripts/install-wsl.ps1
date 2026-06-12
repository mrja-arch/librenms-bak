$ErrorActionPreference = 'Stop'

if (-not (Get-Command wsl.exe -ErrorAction SilentlyContinue)) {
    throw 'WSL is not available on this Windows host.'
}

wsl.exe --install -d Ubuntu-24.04
Write-Host 'Ubuntu 24.04 installation requested. Reboot Windows if prompted, then run install-containerlab.sh inside Ubuntu.'

