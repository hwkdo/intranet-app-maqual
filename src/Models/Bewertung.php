<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppMaqual\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Bewertung extends Model
{
    protected $table = 'intranet_app_maqual_bewertungen';

    protected $guarded = [];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'data' => 'array',
        ];
    }

    public function qual(): BelongsTo
    {
        return $this->belongsTo(Qual::class, 'qual_id');
    }
}
