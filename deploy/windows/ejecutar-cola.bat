@echo off
rem Cola de Laravel: envia los correos de notificacion que quedan en la tabla jobs.
rem Lo lanza la tarea de Windows creada por instalar-tareas.bat. Argumentos:
rem   %1 = ruta de php.exe   %2 = carpeta bin de PostgreSQL (para pg_dump)
rem Si el proceso termina o falla, el bucle lo vuelve a levantar a los 10 s.
setlocal
set "PHP=%~1"
if "%PHP%"=="" set "PHP=php"
if not "%~2"=="" set "PATH=%~2;%PATH%"
cd /d "%~dp0..\.."
if not exist "storage\logs" mkdir "storage\logs"
:bucle
echo [%date% %time%] cola: iniciando>>"storage\logs\tarea-cola.log"
"%PHP%" artisan queue:work --sleep=3 --tries=3 --max-time=3600 >>"storage\logs\tarea-cola.log" 2>&1
echo [%date% %time%] cola: termino (codigo %errorlevel%), reiniciando en 10 s>>"storage\logs\tarea-cola.log"
ping -n 11 127.0.0.1 >nul
goto bucle
