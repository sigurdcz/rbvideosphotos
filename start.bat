@echo off
echo Stopping any existing PHP server...
powershell -Command "Stop-Process -Name php -Force -ErrorAction SilentlyContinue"
timeout /t 1 >nul
echo Starting SEO Mini CMS server...
start "SEO-CMS-Server" /MIN cmd /c "php -S localhost:8000 router.php"
timeout /t 1 >nul
echo.
echo Server running at http://localhost:8000/admin
echo Login: admin@example.com / admin123
echo.
pause
