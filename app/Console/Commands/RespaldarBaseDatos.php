<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\Process\Process;

/**
 * Respaldo automático (02A §9, 02C Fase 11): pg_dump en formato custom,
 * restaurable con pg_restore. Programado en routes/console.php.
 */
class RespaldarBaseDatos extends Command
{
    protected $signature = 'respaldo:base-datos';

    protected $description = 'Genera un respaldo de la base de datos (pg_dump, formato custom) en storage/app/respaldos';

    public function handle(): int
    {
        $conexion = config('database.connections.pgsql');

        Storage::makeDirectory('respaldos');

        $nombreArchivo = 'respaldos/compras_laperla_'.now()->format('Y-m-d_His').'.dump';
        $rutaAbsoluta = Storage::path($nombreArchivo);

        $proceso = new Process([
            'pg_dump',
            '--host='.$conexion['host'],
            '--port='.$conexion['port'],
            '--username='.$conexion['username'],
            '--format=custom',
            '--file='.$rutaAbsoluta,
            $conexion['database'],
        ]);

        $proceso->setEnv(['PGPASSWORD' => $conexion['password']]);
        $proceso->setTimeout(300);
        $proceso->run();

        if (! $proceso->isSuccessful()) {
            $this->error('Falló el respaldo: '.$proceso->getErrorOutput());

            return self::FAILURE;
        }

        $this->info("Respaldo generado: {$nombreArchivo}");

        $this->eliminarRespaldosVencidos();

        return self::SUCCESS;
    }

    private function eliminarRespaldosVencidos(): void
    {
        $limite = now()->subDays(config('respaldo.retencion_dias'));

        foreach (Storage::files('respaldos') as $archivo) {
            if (Storage::lastModified($archivo) < $limite->timestamp) {
                Storage::delete($archivo);
                $this->info("Respaldo vencido eliminado: {$archivo}");
            }
        }
    }
}
