param(
    [string]$EnvFile = "docker/.env.production-200"
)

$ErrorActionPreference = "Stop"
$compose = @(
    "compose",
    "--env-file", $EnvFile,
    "-f", "docker/compose.yml",
    "-f", "docker/compose.production-200.yml"
)

docker @compose up -d
docker @compose exec -T -u librenms librenms php artisan migrate --force

$settings = [ordered]@{
    service_poller_workers = 24
    service_discovery_workers = 8
    service_alerting_workers = 4
    service_services_workers = 4
    service_billing_workers = 2
    service_ping_workers = 8
    service_poller_frequency = 300
    service_discovery_frequency = 21600
    service_alerting_frequency = 60
    service_services_frequency = 300
    eventlog_purge = 90
    syslog_purge = 90
    alert_log_purge = 365
    authlog_purge = 90
    ports_purge = $true
    networks_purge = $true
    rrd_purge = 0
}

foreach ($entry in $settings.GetEnumerator()) {
    $value = if ($entry.Value -is [bool]) { $entry.Value.ToString().ToLowerInvariant() } else { [string]$entry.Value }
    docker @compose exec -T -u librenms librenms php artisan config:set $entry.Key $value
}

docker @compose exec -T -u librenms librenms php validate.php
docker @compose ps
