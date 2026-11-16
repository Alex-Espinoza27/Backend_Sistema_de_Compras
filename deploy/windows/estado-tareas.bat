@echo off
rem Doble clic: muestra si las tareas estan instaladas y corriendo.
powershell -NoProfile -ExecutionPolicy Bypass -File "%~dp0tareas.ps1" -Accion Estado
echo.
pause
