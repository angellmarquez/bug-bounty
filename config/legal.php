<?php

/*
|--------------------------------------------------------------------------
| Documentos legales
|--------------------------------------------------------------------------
|
| `version` identifica la redacción vigente de los Términos de Servicio, la
| Política de Privacidad y la Política de Divulgación. Se guarda con cada
| aceptación (users.terminos_version): al cambiar los textos, sube la versión.
|
| BORRADOR pendiente de revisión legal: los datos entre corchetes se completan
| antes de publicar.
|
*/

return [

    'version' => '2026-09-27',

    'operador' => env('LEGAL_OPERADOR', '[Nombre legal del operador de Huella]'),

    'jurisdiccion' => env('LEGAL_JURISDICCION', '[País y ciudad cuyos tribunales son competentes]'),

    'contacto' => env('LEGAL_CONTACTO', '[correo de contacto legal]'),

];
