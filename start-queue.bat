@echo off
REM Runs the generator worker: takes queued UI generations and sends them to Qwen.
set "PHP=php"
if exist "C:\php\php.exe" set "PHP=C:\php\php.exe"
"%PHP%" -r "exit(version_compare(PHP_VERSION,'8.4.1','<')?1:0);" || (echo. & echo [!] UISpark needs PHP 8.4.1 or newer. Found: & "%PHP%" -v & pause & exit /b 1)
cd /d "%~dp0backend"
echo Generator worker running. Keep this window open while generating.
"%PHP%" artisan queue:work --timeout=0 --tries=1 --sleep=2
pause
