@echo off
echo Restarting SEO Mini CMS server...
powershell -Command "Stop-Process -Name php -Force -ErrorAction SilentlyContinue"
timeout /t 1 >nul
start "SEO-CMS-Server" /MIN cmd /c "php -S localhost:8000 router.php"
timeout /t 1 >nul
echo Server running at http://localhost:8000/admin
echo.
pause
