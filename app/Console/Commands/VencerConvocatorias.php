<?php

namespace App\Console\Commands;

use App\Services\ConvocatoriaService;
use Illuminate\Console\Command;

/**
 * PUBLICADA → EN_EVALUACION al vencer CONV_FECHA_FIN (00 §5, 02C Fase 5).
 * Programado en routes/console.php; nunca se invoca desde un endpoint.
 */
class VencerConvocatorias extends Command
{
    protected $signature = 'convocatorias:vencer';

    protected $description = 'Pasa a EN_EVALUACION las convocatorias PUBLICADA cuya fecha de fin ya venció';

    public function handle(ConvocatoriaService $convocatoriaService): int
    {
        $total = $convocatoriaService->vencerVigencia();

        $this->info("Convocatorias pasadas a EN_EVALUACION: {$total}");

        return self::SUCCESS;
    }
}
