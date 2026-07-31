<?php

return [
    'max_mb' => (int) env('ARCHIVO_MAX_MB', 10),
    'mime_permitidos' => array_values(array_filter(explode(',', (string) env('ARCHIVO_MIME_PERMITIDOS', '')))),
];
