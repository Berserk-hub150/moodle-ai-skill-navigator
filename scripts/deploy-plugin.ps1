$ErrorActionPreference = "Stop"

$BaseDir = "$env:USERPROFILE\Desktop\TESI-MOODLE"
$PluginDir = "$BaseDir\plugins\aiskillnavigator"

Set-Location $BaseDir

docker compose up -d
docker exec -u root tesi-moodle-app bash -lc "rm -rf /opt/bitnami/moodle/local/aiskillnavigator && mkdir -p /opt/bitnami/moodle/local/aiskillnavigator"
docker cp "$PluginDir\." tesi-moodle-app:/opt/bitnami/moodle/local/aiskillnavigator
docker exec -u root tesi-moodle-app bash -lc "chown -R 1001:0 /opt/bitnami/moodle/local/aiskillnavigator && chmod -R 755 /opt/bitnami/moodle/local/aiskillnavigator"
docker exec tesi-moodle-app php /opt/bitnami/moodle/admin/cli/purge_caches.php