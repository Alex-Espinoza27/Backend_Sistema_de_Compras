# Registra la cola y el programador de Laravel como tareas de Windows que
# arrancan con el servidor (aunque nadie inicie sesion) y se reinician solas.
# No dependen de Apache ni de Laragon: solo necesitan PostgreSQL arriba.
# Se usa desde los .bat de esta carpeta; es idempotente (reinstalar reemplaza).
param(
    [Parameter(Mandatory)][ValidateSet('Instalar', 'Desinstalar', 'Estado')][string]$Accion,
    [string]$Php
)
$ErrorActionPreference = 'Stop'

$raiz = (Resolve-Path (Join-Path $PSScriptRoot '..\..')).Path
$proyecto = Split-Path $raiz -Leaf
$tareas = @(
    @{ Nombre = "Laravel-$proyecto-programador"; Bat = 'ejecutar-programador.bat'; Log = 'tarea-programador.log'; Comando = 'schedule:work' },
    @{ Nombre = "Laravel-$proyecto-cola"; Bat = 'ejecutar-cola.bat'; Log = 'tarea-cola.log'; Comando = 'queue:work' }
)

function Buscar-Php {
    if ($Php) { return $Php }
    # 1) El mismo PHP que usa Apache en Laragon.
    $conf = 'C:\laragon\etc\apache2\mod_php.conf'
    if (Test-Path $conf) {
        $m = Select-String -Path $conf -Pattern 'LoadModule\s+php_module\s+"([^"]+)"' | Select-Object -First 1
        if ($m) {
            $dir = Split-Path ($m.Matches[0].Groups[1].Value -replace '/', '\')
            if (Test-Path (Join-Path $dir 'php.exe')) { return (Join-Path $dir 'php.exe') }
        }
    }
    # 2) El de mayor version instalado en Laragon.
    $c = Get-ChildItem 'C:\laragon\bin\php\php-*\php.exe' -ErrorAction SilentlyContinue |
        Sort-Object { [version]($_.Directory.Name -replace '^php-(\d+\.\d+\.\d+).*', '$1') } -Descending |
        Select-Object -First 1
    if ($c) { return $c.FullName }
    # 3) El que haya en el PATH.
    $g = Get-Command php.exe -ErrorAction SilentlyContinue
    if ($g) { return $g.Source }
    throw 'No se encontro php.exe. Reintentar con: tareas.ps1 -Accion Instalar -Php "C:\ruta\php.exe"'
}

function Buscar-PgBin {
    $g = Get-Command pg_dump.exe -ErrorAction SilentlyContinue
    if ($g) { return (Split-Path $g.Source) }
    $c = Get-ChildItem 'C:\laragon\bin\postgresql\*\bin\pg_dump.exe', 'C:\Program Files\PostgreSQL\*\bin\pg_dump.exe' -ErrorAction SilentlyContinue |
        Sort-Object FullName -Descending | Select-Object -First 1
    if ($c) { return $c.DirectoryName }
    return ''
}

function Procesos-Laravel {
    Get-CimInstance Win32_Process -Filter "Name='php.exe'" -ErrorAction SilentlyContinue |
        Where-Object { $_.CommandLine -match 'artisan\s+(queue:work|schedule:work)' }
}

switch ($Accion) {
    'Instalar' {
        $php = Buscar-Php
        $pg = Buscar-PgBin
        Write-Host "Proyecto : $raiz"
        Write-Host "PHP      : $php ($(& $php -r 'echo PHP_VERSION;'))"
        if ($pg) { Write-Host "pg_dump  : $pg" } else { Write-Host 'pg_dump  : NO ENCONTRADO (el respaldo diario fallara hasta agregar la carpeta bin de PostgreSQL al PATH del sistema)' }

        Push-Location $raiz
        try {
            & $php artisan --version
            if ($LASTEXITCODE -ne 0) { throw 'La aplicacion no arranca con ese PHP. Revisar version/extensiones o indicar otro con -Php.' }
        } finally { Pop-Location }

        foreach ($t in $tareas) {
            $bat = Join-Path $PSScriptRoot $t.Bat
            $arg = '/c ""{0}" "{1}" "{2}""' -f $bat, $php, $pg
            $accionTarea = New-ScheduledTaskAction -Execute 'cmd.exe' -Argument $arg -WorkingDirectory $raiz
            $disparador = New-ScheduledTaskTrigger -AtStartup
            $config = New-ScheduledTaskSettingsSet -AllowStartIfOnBatteries -DontStopIfGoingOnBatteries `
                -StartWhenAvailable -MultipleInstances IgnoreNew `
                -RestartCount 999 -RestartInterval (New-TimeSpan -Minutes 1) `
                -ExecutionTimeLimit ([TimeSpan]::Zero)
            $principal = New-ScheduledTaskPrincipal -UserId 'NT AUTHORITY\SYSTEM' -LogonType ServiceAccount -RunLevel Highest

            if (Get-ScheduledTask -TaskName $t.Nombre -ErrorAction SilentlyContinue) {
                Stop-ScheduledTask -TaskName $t.Nombre -ErrorAction SilentlyContinue
            }
            Register-ScheduledTask -TaskName $t.Nombre -Action $accionTarea -Trigger $disparador `
                -Settings $config -Principal $principal -Force `
                -Description "Laravel $($t.Comando) de $proyecto (arranca con Windows)" | Out-Null
            Start-ScheduledTask -TaskName $t.Nombre
            Write-Host "OK: tarea '$($t.Nombre)' instalada e iniciada."
        }
        Write-Host ''
        Write-Host 'Listo. Arrancan solas al encender el servidor. Tras cada despliegue con cambios de codigo: php artisan queue:restart'
    }
    'Desinstalar' {
        foreach ($t in $tareas) {
            if (Get-ScheduledTask -TaskName $t.Nombre -ErrorAction SilentlyContinue) {
                Stop-ScheduledTask -TaskName $t.Nombre -ErrorAction SilentlyContinue
                Unregister-ScheduledTask -TaskName $t.Nombre -Confirm:$false
                Write-Host "Tarea '$($t.Nombre)' eliminada."
            } else {
                Write-Host "Tarea '$($t.Nombre)' no estaba instalada."
            }
        }
    }
    'Estado' {
        foreach ($t in $tareas) {
            $tarea = Get-ScheduledTask -TaskName $t.Nombre -ErrorAction SilentlyContinue
            Write-Host "== $($t.Nombre)"
            if (-not $tarea) { Write-Host '   NO instalada (ejecutar instalar-tareas.bat)'; continue }
            $info = $tarea | Get-ScheduledTaskInfo
            Write-Host "   Estado        : $($tarea.State)"
            Write-Host "   Ultimo inicio : $($info.LastRunTime)"
            $log = Join-Path $raiz "storage\logs\$($t.Log)"
            if (Test-Path $log) {
                Write-Host '   Ultimas lineas del log:'
                Get-Content $log -Tail 3 | ForEach-Object { Write-Host "     $_" }
            }
        }
        $vivos = @(Procesos-Laravel)
        Write-Host ''
        Write-Host "Procesos php de cola/programador corriendo en este servidor: $($vivos.Count)"
    }
}
