param(
    [string]$Message = "Update client plugin",
    [switch]$SkipSyntaxCheck
)

$ErrorActionPreference = "Stop"

$repoRoot = Resolve-Path (Join-Path $PSScriptRoot "..")
Set-Location $repoRoot

if (-not $SkipSyntaxCheck) {
    $php = Get-Command php -ErrorAction SilentlyContinue
    if ($php) {
        Get-ChildItem -Recurse -Filter "*.php" |
            Where-Object { $_.FullName -notmatch "\\.git\\" -and $_.FullName -notmatch "\\dist\\" } |
            ForEach-Object {
                php -l $_.FullName
            }
    } else {
        Write-Warning "php command was not found. Skipping PHP syntax checks."
    }
}

$secretPatterns = @(
    "BEGIN RSA PRIVATE KEY",
    "BEGIN OPENSSH PRIVATE KEY",
    "BEGIN PRIVATE KEY"
)

$scanFiles = Get-ChildItem -Recurse -File |
    Where-Object {
        $_.FullName -notmatch "\\.git\\" -and
        $_.FullName -notmatch "\\dist\\" -and
        $_.FullName -notmatch "\\tools\\ok-git\.ps1$" -and
        $_.FullName -notmatch "\\node_modules\\" -and
        $_.Extension -notin @(".png", ".jpg", ".jpeg", ".gif", ".zip")
    }

foreach ($pattern in $secretPatterns) {
    $matches = $scanFiles | Select-String -Pattern $pattern -SimpleMatch -ErrorAction SilentlyContinue
    if ($matches) {
        Write-Error "Potential secret-like text found for pattern '$pattern'. Please review before pushing."
    }
}

$assignmentPatterns = @(
    "FREEMIUS_ACCESS_TOKEN\s*=\s*['""][^'""]{12,}['""]",
    "FREEMIUS_API_TOKEN\s*=\s*['""][^'""]{12,}['""]",
    "FREEMIUS_SECRET\w*\s*=\s*['""][^'""]{12,}['""]",
    "secret_key\s*=\s*['""][^'""]{12,}['""]",
    "private_key\s*=\s*['""][^'""]{12,}['""]"
)

foreach ($pattern in $assignmentPatterns) {
    $matches = $scanFiles | Select-String -Pattern $pattern -ErrorAction SilentlyContinue
    if ($matches) {
        Write-Error "Potential configured secret found. Please remove it before pushing."
    }
}

$status = git status --short
if (-not $status) {
    Write-Host "No changes to commit."
    git status --short --branch
    exit 0
}

git add .
git commit -m $Message
git push
git status --short --branch
