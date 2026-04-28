param(
    [ValidateSet("pending", "beta", "released")]
    [string]$ReleaseMode = "pending",
    [string]$ProductId = $env:FREEMIUS_PRODUCT_ID,
    [string]$AccessToken = $env:FREEMIUS_API_TOKEN,
    [switch]$PackageOnly
)

$ErrorActionPreference = "Stop"

$repoRoot = Resolve-Path (Join-Path $PSScriptRoot "..")
Set-Location $repoRoot

$mainFile = Join-Path $repoRoot "mimamori-client-agent.php"
$mainText = Get-Content -LiteralPath $mainFile -Raw -Encoding UTF8
if ($mainText -notmatch "Version:\s*([0-9]+\.[0-9]+\.[0-9][0-9A-Za-z\.\-\+]*)") {
    throw "Could not detect plugin version from mimamori-client-agent.php."
}

$version = $Matches[1]
$slug = "mimamori-client-agent"
$dist = Join-Path $repoRoot "dist"
$zip = Join-Path $dist "$slug-$version.zip"

New-Item -ItemType Directory -Path $dist -Force | Out-Null
if (Test-Path -LiteralPath $zip) {
    Remove-Item -LiteralPath $zip -Force
}

# Freemius recommends avoiding PowerShell-generated ZIPs on Windows. git archive
# creates a stable ZIP and includes the plugin root directory via --prefix.
git archive --format=zip --prefix="$slug/" --output="$zip" HEAD

Write-Host "Created package: $zip"

if ($PackageOnly) {
    exit 0
}

if (-not $ProductId -or -not $AccessToken) {
    Write-Host "Freemius credentials are not configured. Set these environment variables and run again:"
    Write-Host '  $env:FREEMIUS_PRODUCT_ID="12345"'
    Write-Host '  $env:FREEMIUS_API_TOKEN="..."'
    Write-Host "Package was created but not uploaded."
    exit 2
}

$uploadUrl = "https://api.freemius.com/v1/products/$ProductId/tags.json"
$upload = curl.exe -sS -X POST $uploadUrl `
    -H "Authorization: Bearer $AccessToken" `
    -F "file=@$zip"

if ($LASTEXITCODE -ne 0) {
    throw "Freemius upload request failed."
}

$uploadJson = $upload | ConvertFrom-Json
$tagId = $uploadJson.id
if (-not $tagId) {
    Write-Host $upload
    throw "Freemius upload response did not include a deployment id."
}

Write-Host "Uploaded Freemius deployment id: $tagId"

if ($ReleaseMode -ne "pending") {
    $body = @{
        release_mode = $ReleaseMode
        has_premium = $false
    } | ConvertTo-Json -Compress

    $updateUrl = "https://api.freemius.com/v1/products/$ProductId/tags/$tagId.json"
    $update = curl.exe -sS -X PUT $updateUrl `
        -H "Authorization: Bearer $AccessToken" `
        -H "Content-Type: application/json" `
        --data $body

    if ($LASTEXITCODE -ne 0) {
        throw "Freemius release mode update failed."
    }

    Write-Host "Updated Freemius deployment release mode to: $ReleaseMode"
}
