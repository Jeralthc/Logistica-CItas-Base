$dir = 'c:\laragon\www\Logistica-Citas-Base\resources'
$files = Get-ChildItem -Path $dir -Recurse -File -Include *.vue, *.blade.php
foreach ($file in $files) {
    $content = Get-Content $file.FullName -Raw
    if ($content -match 'red-') {
        $content = $content -replace 'red-', 'indigo-'
        Set-Content -Path $file.FullName -Value $content -NoNewline
        Write-Host "Updated theme color in: $($file.FullName)"
    }
}
