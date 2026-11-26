# Builds dist\dnn-pools-cpanel.zip: everything the server needs, so cPanel needs no Node or Composer.
# Run it from anywhere:  .\scripts\package-cpanel.ps1
# See DEPLOYMENT.md.

# Cmdlets stop on error. Programs (npm, composer, tar) are judged by their exit code instead:
# with ErrorActionPreference = 'Stop', Windows PowerShell 5.1 treats a mere warning on their
# error stream as fatal whenever the output is captured.
$ErrorActionPreference = 'Continue'
$PSDefaultParameterValues['*:ErrorAction'] = 'Stop'

$root = Split-Path -Parent $PSScriptRoot
$dist = Join-Path $root 'dist'
$zip = Join-Path $dist 'dnn-pools-cpanel.zip'
$stage = Join-Path ([IO.Path]::GetTempPath()) ('dnn-pools-package-' + [Guid]::NewGuid().ToString('N'))

function Assert-Success($step) {
    if ($LASTEXITCODE -ne 0) { throw "$step failed (exit code $LASTEXITCODE)." }
}

Push-Location $root
try {
    Write-Host 'Building the web assets...'
    npm run build
    Assert-Success 'npm run build'

    Write-Host 'Copying the application...'
    New-Item -ItemType Directory -Force $stage | Out-Null

    # Only files git would keep, so .env, public\hot, the local database and logs are never included.
    $files = git -c core.quotepath=off ls-files --cached --others --exclude-standard
    Assert-Success 'git ls-files'
    # The single-file Livewire views have a non-ASCII character in their names.
    $skip = '^(tests/|\.github/|dist/|scripts/|phpunit\.xml$|phpstan\.neon$)'

    foreach ($file in ($files | Where-Object { $_ -notmatch $skip })) {
        $source = Join-Path $root $file
        if (-not (Test-Path -LiteralPath $source -PathType Leaf)) { continue }

        $target = Join-Path $stage $file
        New-Item -ItemType Directory -Force (Split-Path -Parent $target) | Out-Null
        Copy-Item -LiteralPath $source -Destination $target
    }

    New-Item -ItemType Directory -Force (Join-Path $stage 'public') | Out-Null
    Copy-Item -Recurse (Join-Path $root 'public\build') (Join-Path $stage 'public\build')

    Write-Host 'Installing the production PHP packages...'
    composer install --working-dir="$stage" --no-dev --optimize-autoloader --no-interaction --no-progress
    Assert-Success 'composer install'

    # Caches made on this PC must not travel to the server.
    Remove-Item (Join-Path $stage 'bootstrap\cache\*.php') -Force -ErrorAction SilentlyContinue

    foreach ($forbidden in '.env', 'public\hot', 'database\database.sqlite') {
        if (Test-Path (Join-Path $stage $forbidden)) { throw "$forbidden must not be in the package." }
    }

    Write-Host 'Compressing...'
    New-Item -ItemType Directory -Force $dist | Out-Null
    if (Test-Path $zip) { Remove-Item $zip -Force }
    # Built entry by entry so that paths use forward slashes, which Linux needs; Compress-Archive
    # in Windows PowerShell 5.1 may write backslashes.
    Add-Type -AssemblyName System.IO.Compression, System.IO.Compression.FileSystem
    # The long form of the path: the temp folder may be given as a short 8.3 name.
    $base = (Get-Item -LiteralPath $stage).FullName.TrimEnd('\') + '\'
    $packaged = @(Get-ChildItem -LiteralPath $stage -Recurse -File -Force)

    $archive = [IO.Compression.ZipFile]::Open($zip, [IO.Compression.ZipArchiveMode]::Create)
    try {
        foreach ($item in $packaged) {
            if (-not $item.FullName.StartsWith($base)) { throw "Unexpected path: $($item.FullName)" }
            $name = $item.FullName.Substring($base.Length).Replace('\', '/')
            [void][IO.Compression.ZipFileExtensions]::CreateEntryFromFile($archive, $item.FullName, $name)
        }
    }
    finally {
        $archive.Dispose()
    }

    # Read it back: a damaged or incomplete package must not reach the server.
    $check = [IO.Compression.ZipFile]::OpenRead($zip)
    try {
        $names = @($check.Entries | ForEach-Object { $_.FullName })
    }
    finally {
        $check.Dispose()
    }

    if ($names.Count -ne $packaged.Count) { throw "The zip holds $($names.Count) of $($packaged.Count) files." }

    foreach ($required in 'artisan', 'public/index.php', 'public/.htaccess', 'public/build/manifest.json', 'vendor/autoload.php', 'bootstrap/cache/.gitignore') {
        if ($names -cnotcontains $required) { throw "$required is missing from the package." }
    }

    Write-Host "Done: $zip"
}
finally {
    Pop-Location
    if (Test-Path -LiteralPath $stage) { Remove-Item -LiteralPath $stage -Recurse -Force }
}
