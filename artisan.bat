@echo off
REM Wrapper local para ejecutar artisan directamente con PHP 8.3.30
REM Solo afecta ejecuciones dentro de esta carpeta
"C:\laragon\bin\php\php-8.3.30-Win32-vs16-x64\php.exe" "%~dp0artisan" %*
