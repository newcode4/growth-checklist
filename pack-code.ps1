param(
  [string]$Root = ".",
  [string]$Out = "codepack.txt",
  [string]$IncludeExt = ".php,.js,.ts,.css,.scss,.html,.htm,.json,.md,.yml,.yaml,.xml,.sh,.ps1,.ini,.txt,.sql,.twig,.vue",
  # 제외할 디렉터리들 (쉼표로 구분)
  [string]$ExcludeDirs = "node_modules,dist,build,vendor,.git,.github,.idea,.vscode,.next,.cache,growth-checklist\plugin-update-checker",
  # 제외할 파일 패턴들 (쉼표로 구분)
  [string]$ExcludeFiles = "*.min.js,*.min.css,*.map,*.lock,*.zip,*.tar,*.gz,*.png,*.jpg,*.jpeg,*.gif,*.webp,*.svg,*.woff,*.woff2,*.ttf,*.eot,*.pdf",
  # 용량 한도
  [int]$MaxFileBytes = 1000000,    # 파일당 최대 1MB
  [int]$MaxTotalBytes = 20000000,  # 전체 최대 20MB
  # 변경 파일만 모드
  [switch]$ChangedOnly,
  [string]$GitRange = ""
)

$ErrorActionPreference = "Stop"
$Root = (Resolve-Path $Root).Path
$include = $IncludeExt.Split(",") | ForEach-Object { $_.Trim().ToLower() } | Where-Object { $_ -ne "" }
$exDirs  = $ExcludeDirs.Split(",")  | ForEach-Object { $_.Trim() }        | Where-Object { $_ -ne "" }
$exFiles = $ExcludeFiles.Split(",") | ForEach-Object { $_.Trim() }        | Where-Object { $_ -ne "" }

function IsTextExt($path) {
  $ext = [IO.Path]::GetExtension($path).ToLower()
  return $include -contains $ext
}
function IsExcludedDir($path) {
  foreach ($d in $exDirs) {
    if ($path -like "*$d*") { return $true }
  }
  return $false
}
function IsExcludedFile($file) {
  foreach ($p in $exFiles) {
    if ([IO.Path]::GetFileName($file) -like $p) { return $true }
  }
  return $false
}

# 파일 목록 수집
$files = @()
if ($ChangedOnly) {
  $gitArgs = @("diff","--name-only")
  if ($GitRange) { $gitArgs += $GitRange }
  $changed = & git @gitArgs 2>$null
  foreach ($rel in $changed) {
    $p = Join-Path $Root $rel
    if (Test-Path $p -PathType Leaf) { $files += (Resolve-Path $p).Path }
  }
} else {
  $files = Get-ChildItem -Path $Root -Recurse -File | ForEach-Object { $_.FullName }
}

# 필터링
$files = $files | Where-Object {
  -not (IsExcludedDir $_) -and
  -not (IsExcludedFile $_) -and
  (IsTextExt $_)
}

# 출력 빌드
$sb = New-Object System.Text.StringBuilder
$totalBytes = 0
$nl = [Environment]::NewLine

# 상단 메타
try {
  $repoUrl = (git -C $Root config --get remote.origin.url)
  $branch  = (git -C $Root rev-parse --abbrev-ref HEAD)
  $tag     = (git -C $Root describe --tags --abbrev=0) 2>$null
  $commit  = (git -C $Root rev-parse --short HEAD)
  $time    = Get-Date -Format "yyyy-MM-dd HH:mm:ss"
  $null = $sb.AppendLine("===== META =====")
  $null = $sb.AppendLine("Repo: $repoUrl")
  $null = $sb.AppendLine("Branch: $branch  |  Tag: $tag  |  Commit: $commit")
  $null = $sb.AppendLine("Generated: $time")
  $null = $sb.AppendLine("")
} catch {}

foreach ($f in $files) {
  try {
    $fi = Get-Item $f
    $size = $fi.Length
    if ($size -gt $MaxFileBytes) {
      $rel = $f.Substring($Root.Length+1)
      $null = $sb.AppendLine("===== FILE: $rel ($size bytes) SKIPPED: > $MaxFileBytes =====$nl")
      continue
    }
    if (($totalBytes + $size) -gt $MaxTotalBytes) {
      $null = $sb.AppendLine("===== STOP: total size would exceed $MaxTotalBytes bytes. Remaining files omitted. =====$nl")
      break
    }

    $rel = $f.Substring($Root.Length+1)
    $content = (Get-Content -Path $f -Raw -Encoding UTF8)
    $lineCount = ($content -split "`r?`n").Count
    $ext = [IO.Path]::GetExtension($f).TrimStart(".").ToLower()
    if (-not $ext) { $ext = "text" }

    $null = $sb.AppendLine("===== FILE: $rel ($size bytes, $lineCount lines) =====")
    $null = $sb.AppendLine("```$ext")
    $null = $sb.AppendLine($content)
    $null = $sb.AppendLine("```$nl")

    $totalBytes += $size
  } catch {
    $null = $sb.AppendLine("===== FILE: $f ERROR: $($_.Exception.Message) =====$nl")
  }
}

[IO.File]::WriteAllText((Join-Path $Root $Out), $sb.ToString(), [Text.Encoding]::UTF8)
"생성 완료: $(Join-Path $Root $Out)  (총 approx $totalBytes bytes)"
