$dir = 'C:\laragon\www\logistica-citas\resources'
$files = Get-ChildItem -Path $dir -Recurse -File -Include *.vue, *.blade.php
foreach ($file in $files) {
    $content = [System.IO.File]::ReadAllText($file.FullName)
    $modified = $false

    if ($content -match '/images/logo\.png') {
        $content = $content -replace '/images/logo\.png', '/images/logo_suraki.ico'
        $modified = $true
    }
    
    if ($modified) {
        $utf8NoBom = New-Object System.Text.UTF8Encoding $false
        [System.IO.File]::WriteAllText($file.FullName, $content, $utf8NoBom)
        Write-Host "Updated with UTF-8: $($file.FullName)"
    }
}
