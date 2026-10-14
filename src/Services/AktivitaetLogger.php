<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppMaqual\Services;

use Hwkdo\IntranetAppMaqual\Enums\AktivitaetTyp;
use Hwkdo\IntranetAppMaqual\Enums\QualStatus;
use Hwkdo\IntranetAppMaqual\Models\Aktivitaet;
use Hwkdo\IntranetAppMaqual\Models\Qual;
use Illuminate\Database\Eloquent\Model;

class AktivitaetLogger
{
    public function status(Qual $qual, Model $user, ?QualStatus $von, QualStatus $nach, ?string $bemerkung = null): void
    {
        Aktivitaet::query()->create([
            'qual_id' => $qual->getKey(),
            'user_id' => $user->getKey(),
            'typ' => AktivitaetTyp::StatusChange,
            'vorher' => ['status' => $von?->value],
            'nachher' => ['status' => $nach->value],
            'bemerkung' => $bemerkung,
        ]);
    }

    /**
     * @param  array<string, mixed>  $vorher
     * @param  array<string, mixed>  $nachher
     */
    public function felder(Qual $qual, Model $user, array $vorher, array $nachher, AktivitaetTyp $typ, ?string $bemerkung = null): void
    {
        Aktivitaet::query()->create([
            'qual_id' => $qual->getKey(),
            'user_id' => $user->getKey(),
            'typ' => $typ,
            'vorher' => $vorher,
            'nachher' => $nachher,
            'bemerkung' => $bemerkung,
        ]);
    }

    public function datei(Qual $qual, Model $user, string $collection, ?string $alterName, string $neuerName): void
    {
        Aktivitaet::query()->create([
            'qual_id' => $qual->getKey(),
            'user_id' => $user->getKey(),
            'typ' => AktivitaetTyp::FileReplace,
            'vorher' => ['collection' => $collection, 'datei' => $alterName],
            'nachher' => ['collection' => $collection, 'datei' => $neuerName],
        ]);
    }

    /**
     * @param  array<string, mixed>|null  $vorher
     * @param  array<string, mixed>  $nachher
     */
    public function budget(Model $user, ?array $vorher, array $nachher): void
    {
        Aktivitaet::query()->create([
            'qual_id' => null,
            'user_id' => $user->getKey(),
            'typ' => AktivitaetTyp::BudgetChange,
            'vorher' => $vorher,
            'nachher' => $nachher,
        ]);
    }
}
