# Windows PowerShell quick start

This quick start covers cloning the repository, checking the expected plugin layout, and running basic local validation from Windows PowerShell. It does not require API keys, credentials, or any other secrets.

## Clone the repository

```powershell
git clone https://github.com/Berserk-hub150/moodle-ai-skill-navigator.git
Set-Location .\moodle-ai-skill-navigator
```

## Check the plugin layout

The repository contains the local plugin and the optional block under `plugins`:

```powershell
Get-ChildItem .\plugins
Test-Path .\plugins\aiskillnavigator
Test-Path .\plugins\block_aiskillnavigator
```

Both `Test-Path` commands should return `True`.

For Moodle installation, `plugins\aiskillnavigator` must be placed at `local\aiskillnavigator`, while `plugins\block_aiskillnavigator` must be placed at `blocks\aiskillnavigator`. See the [full installation instructions](../README.md#installation) before copying files into a Moodle instance.

## Basic validation

If PHP is available on your `PATH`, check the PHP files for syntax errors:

```powershell
Get-ChildItem .\plugins -Recurse -Filter *.php | ForEach-Object {
    php -l $_.FullName
}
```

If Node.js is available, check JavaScript syntax as well:

```powershell
Get-ChildItem .\plugins -Recurse -Filter *.js | ForEach-Object {
    node --check $_.FullName
}
```

For the complete contributor validation flow, including the repository tests and Moodle runtime checks, continue with [CONTRIBUTING.md](../CONTRIBUTING.md).
