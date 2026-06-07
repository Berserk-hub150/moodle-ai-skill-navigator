$ErrorActionPreference = "Stop"

$BaseDir = "$env:USERPROFILE\Desktop\TESI-MOODLE"
Set-Location $BaseDir

$files = docker exec tesi-moodle-app bash -lc "find /opt/bitnami/moodle/local/aiskillnavigator -name '*.php' -type f"

foreach ($file in $files) {
    docker exec tesi-moodle-app php -l $file
}