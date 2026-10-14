<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppMaqual\Support;

use Hwkdo\IntranetAppMaqual\Models\Qual;
use Illuminate\Database\Eloquent\Model;

class RegistersMaqualUserRelations
{
    public static function register(): void
    {
        $userClass = MaqualModels::user();

        $userClass::resolveRelationUsing(
            'maqualQuals',
            fn (Model $user) => $user->hasMany(Qual::class, 'teilnehmer_id'),
        );
    }
}
