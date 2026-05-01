@echo off
echo Stopping SEO Mini CMS server...
powershell -Command "Stop-Process -Name php -Force -ErrorAction SilentlyContinue"
echo Server stopped.
pause
