<?php

/*
|--------------------------------------------------------------------------
| Fotos adjuntas (evidencia de informes y apelaciones)
|--------------------------------------------------------------------------
|
| Cada foto se re-codifica (se descartan EXIF/GPS y cualquier contenido que
| no sea la imagen), se cifra con PGP a las mismas claves que el informe y
| solo entonces se escribe en el disco. Nunca tiene URL pública: se sirve
| por una ruta que pasa por ABAC y se descifra al vuelo.
|
| En local el disco es `local` (storage/app/private). En Render el disco del
| servicio se borra en cada deploy: allí hay que usar `s3` (Supabase Storage).
| Ver docs/render.md.
|
*/

return [

    'disco' => env('ADJUNTOS_DISK', 'local'),

    'directorio' => 'adjuntos',

    // Tamaño máximo por foto en KB. PHP también debe permitirlo:
    // upload_max_filesize y post_max_size (php.ini) por encima de este valor.
    'max_kb' => (int) env('ADJUNTOS_MAX_KB', 5120),

    // Fotos como máximo por informe o por apelación.
    'max_por_entidad' => (int) env('ADJUNTOS_MAX_POR_ENTIDAD', 10),

    // Lado mayor tras procesar: las fotos más grandes se reducen (ahorra memoria y disco).
    'max_lado' => (int) env('ADJUNTOS_MAX_LADO', 2560),

    // Límite de píxeles de la imagen original: evita "bombas" de descompresión y que GD
    // agote la memoria de PHP (16 MP ≈ 64 MB con memory_limit=128M).
    'max_pixeles' => (int) env('ADJUNTOS_MAX_PIXELES', 16_000_000),

    'mimes' => ['image/png', 'image/jpeg', 'image/webp'],

];
