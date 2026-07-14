$dir = 'c:\laragon\www\Logistica-Citas-Base'
$files = Get-ChildItem -Path $dir -Recurse -File -Include *.php, *.vue, *.js, *.env.example, *.html
foreach ($file in $files) {
    $content = Get-Content $file.FullName -Raw
    if ($content -match 'Suraki|hipersurakica@gmail\.com|sistemassuraki@gmail\.com|Hipersuraki') {
        $content = $content -replace 'sistemassuraki@gmail\.com', 'soporte@tuempresa.com'
        $content = $content -replace 'hipersurakica@gmail\.com', 'contacto@tuempresa.com'
        $content = $content -replace 'HIPER SURAKI', 'TU EMPRESA'
        $content = $content -replace 'Hipersuraki', 'Tu Empresa'
        $content = $content -replace 'Portal Logístico Suraki', 'Portal Logístico Base'
        $content = $content -replace 'Departamento de Sistemas de Suraki', 'Departamento de Sistemas'
        $content = $content -replace 'SURAKI LOGÍSTICA', 'SISTEMA DE LOGÍSTICA'
        $content = $content -replace 'Logística Suraki', 'Sistema de Logística'
        $content = $content -replace 'Suraki', 'Empresa Base'
        $content = $content -replace 'SURAKI', 'EMPRESA BASE'
        Set-Content -Path $file.FullName -Value $content -NoNewline
        Write-Host "Updated: $($file.FullName)"
    }
}
