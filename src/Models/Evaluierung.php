<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppMaqual\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Evaluierung extends Model
{
    protected $table = 'intranet_app_maqual_evaluierungen';

    protected $guarded = [];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'data' => 'array',
            'teilnehmer_bestaetigt_am' => 'datetime',
            'vorgesetzter_bestaetigt_am' => 'datetime',
        ];
    }

    public function qual(): BelongsTo
    {
        return $this->belongsTo(Qual::class, 'qual_id');
    }

    public function istVollstaendig(): bool
    {
        return $this->teilnehmer_bestaetigt_am !== null
            && $this->vorgesetzter_bestaetigt_am !== null;
    }
}
