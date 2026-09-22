@echo off
REM Wrapper local de PHP 8.3 para el proyecto logistica-citas
REM Este archivo hace que al ejecutar "php" dentro de esta carpeta en CMD,
REM se use PHP 8.3.30 en lugar de la version global (8.2.12)
"C:\laragon\bin\php\php-8.3.30-Win32-vs16-x64\php.exe" %*
