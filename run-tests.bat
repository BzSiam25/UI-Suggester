@echo off
REM Runs the backend test suite and saves the result to backend\storage\logs\test-output.txt
set "PHP=php"
if exist "C:\php\php.exe" set "PHP=C:\php\php.exe"
cd /d "%~dp0backend"
"%PHP%" artisan test > storage\logs\test-output.txt 2>&1
echo exit=%ERRORLEVEL%>> storage\logs\test-output.txt
