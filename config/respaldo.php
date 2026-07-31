<?php

return [
    'hora' => env('BACKUP_HORA', '02:00'),
    'retencion_dias' => (int) env('BACKUP_RETENCION_DIAS', 14),
];
