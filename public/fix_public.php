<?php
// Crea un enlace simbólico: public -> public_html
// Para que Laravel encuentre sus archivos build en la ruta correcta
// Sube a public_html/ y abre: https://citsur.Empresa Base.net/fix_public.php

$root = realpath(__DIR__ . '/..');
$publicLink = $root . '/public';
$publicHtml = $root . '/public_html';

echo "<h2>🔧 Configurando enlace public -> public_html</h2>";

// Si ya existe public como directorio real, lo eliminamos (debería estar vacío)
if (is_dir($publicLink) && !is_link($publicLink)) {
    // Verificar si tiene contenido
    $files = scandir($publicLink);
    $isEmpty = count($files) <= 2; // solo . y ..
    if ($isEmpty) {
        rmdir($publicLink);
        echo "<p style='color:orange'>⚠️ Carpeta 'public' vacía eliminada.</p>";
    } else {
        echo "<p style='color:red'>❌ La carpeta 'public' existe y tiene archivos. Elimínala manualmente desde cPanel.</p>";
        unlink(__FILE__);
        exit;
    }
}

// Si ya existe como symlink, reportar
if (is_link($publicLink)) {
    $target = readlink($publicLink);
    echo "<p style='color:green'>✅ El enlace simbólico ya existe: public -> $target</p>";
} else {
    // Crear el symlink
    if (symlink($publicHtml, $publicLink)) {
        echo "<p style='color:green'>✅ Enlace simbólico creado: public -> public_html</p>";
    } else {
        echo "<p style='color:red'>❌ No se pudo crear el enlace simbólico. Intentando método alternativo...</p>";
        
        // Método alternativo: crear archivo .htaccess o modificar el provider
        // Vamos a registrar el public path en el AppServiceProvider
        $providerPath = $root . '/app/Providers/AppServiceProvider.php';
        if (file_exists($providerPath)) {
            $content = file_get_contents($providerPath);
            if (strpos($content, 'usePublicPath') === false) {
                // Inyectar en el método boot()
                $content = str_replace(
                    'public function boot(): void',
                    "public function boot(): void\n    {\n        // Ajustar ruta pública para cPanel\n        \$this->app->usePublicPath(realpath(base_path('public_html')));",
                    $content
                );
                // Eliminar el { duplicado
                $content = str_replace("void\n    {\n        // Ajustar ruta pública para cPanel\n        \$this->app->usePublicPath(realpath(base_path('public_html')));\n    {\n", "void\n    {\n        // Ajustar ruta pública para cPanel\n        \$this->app->usePublicPath(realpath(base_path('public_html')));\n", $content);
                file_put_contents($providerPath, $content);
                echo "<p style='color:green'>✅ AppServiceProvider modificado para usar public_html.</p>";
            } else {
                echo "<p style='color:gray'>⏩ AppServiceProvider ya tenía la configuración.</p>";
            }
        }
    }
}

// Verificar que el manifest existe
$manifestPath = $publicHtml . '/build/manifest.json';
if (file_exists($manifestPath)) {
    echo "<p style='color:green'>✅ manifest.json encontrado en public_html/build/</p>";
} else {
    echo "<p style='color:red'>❌ manifest.json NO encontrado. Necesitas subir DEPLOY_PARTE2_BUILD.zip y extraerlo.</p>";
}

echo "<h3>Recarga tu página principal con Ctrl+F5</h3>";
unlink(__FILE__);
echo "<p style='color:gray;font-size:11px'>Script eliminado.</p>";
