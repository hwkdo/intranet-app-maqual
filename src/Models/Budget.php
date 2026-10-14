<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppMaqual\Models;

use Hwkdo\IntranetAppMaqual\Support\MaqualModels;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Budget extends Model
{
    protected $table = 'intranet_app_maqual_budgets';

    protected $guarded = [];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'kostenjahr' => 'integer',
            'betrag' => 'decimal:2',
        ];
    }

    public function gvp(): BelongsTo
    {
        return $this->belongsTo(MaqualModels::gvp(), 'gvp_id');
    }
}
