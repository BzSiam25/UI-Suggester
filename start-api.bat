@echo off
REM Starts the UISpark backend (Laravel :8000). Applies new migrations and generator options first.
set "PHP=php"
if exist "C:\php\php.exe" set "PHP=C:\php\php.exe"
"%PHP%" -r "exit(version_compare(PHP_VERSION,'8.4.1','<')?1:0);" || (echo. & echo [!] UISpark needs PHP 8.4.1 or newer. Found: & "%PHP%" -v & pause & exit /b 1)
cd /d "%~dp0backend"
echo Using PHP: %PHP%
"%PHP%" artisan migrate --force
"%PHP%" artisan db:seed --class=GeneratorOptionSeeder --force
"%PHP%" artisan serve --host=127.0.0.1 --port=8000
pause
