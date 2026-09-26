<?php

namespace App\Models;

use App\Services\Adjuntos\AdjuntoService;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;

/**
 * Foto de evidencia adjunta a un informe o a una apelación. El archivo está
 * cifrado con PGP en `disco`/`ruta`; ver {@see AdjuntoService}.
 *
 * @property int $id
 * @property string $adjuntable_type
 * @property int $adjuntable_id
 * @property int|null $subido_por
 * @property string $nombre_original
 * @property string $mime
 * @property int $tamano
 * @property int $ancho
 * @property int $alto
 * @property string $sha256
 * @property string $disco
 * @property string $ruta
 * @property string|null $clave_huella
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Model $adjuntable
 * @property-read User|null $autor
 */
#[Fillable(['adjuntable_type', 'adjuntable_id', 'subido_por', 'nombre_original', 'mime', 'tamano', 'ancho', 'alto', 'sha256', 'disco', 'ruta', 'clave_huella'])]
#[Hidden(['disco', 'ruta', 'clave_huella'])]
class Adjunto extends Model
{
    protected $table = 'adjuntos';

    /**
     * @return MorphTo<Model, $this>
     */
    public function adjuntable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function autor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'subido_por');
    }

    public function extension(): string
    {
        return match ($this->mime) {
            'image/png' => 'png',
            'image/webp' => 'webp',
            default => 'jpg',
        };
    }
}
