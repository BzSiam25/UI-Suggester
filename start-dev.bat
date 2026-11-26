@echo off
REM Starts UISpark: backend (Laravel :8000), generator worker (Qwen queue) and frontend (Vite :5173)
start "UISpark API" cmd /c ""%~dp0start-api.bat""
timeout /t 8 /nobreak >nul
start "UISpark Generator Worker" cmd /c ""%~dp0start-queue.bat""
start "UISpark Web" cmd /k "cd /d "%~dp0frontend" && npm run dev"
