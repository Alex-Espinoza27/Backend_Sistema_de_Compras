@echo off
rem Doble clic: quita las tareas de cola y programador.
net session >nul 2>&1
if errorlevel 1 (
  echo Se necesitan permisos de administrador. Aceptar la ventana que aparece...
  powershell -NoProfile -Command "Start-Process -FilePath '%~f0' -Verb RunAs"
  exit /b
)
powershell -NoProfile -ExecutionPolicy Bypass -File "%~dp0tareas.ps1" -Accion Desinstalar
echo.
pause
