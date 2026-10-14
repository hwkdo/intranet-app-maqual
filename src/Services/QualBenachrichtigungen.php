<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppMaqual\Services;

use Hwkdo\IntranetAppMaqual\Enums\QualStatus;
use Hwkdo\IntranetAppMaqual\Models\Qual;
use Hwkdo\IntranetAppMaqual\Notifications\EntscheidungNotification;
use Hwkdo\IntranetAppMaqual\Notifications\FreigabeErforderlichNotification;
use Hwkdo\IntranetAppMaqual\Notifications\NichtTeilgenommenNotification;
use Hwkdo\IntranetAppMaqual\Notifications\TeilnehmerAktionNotification;
use Hwkdo\IntranetAppMaqual\Support\MaqualModels;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

class QualBenachrichtigungen
{
    public function naechsteFreigabe(Qual $qual): void
    {
        $empfaenger = match ($qual->status) {
            QualStatus::Neu => $this->user($qual->al_id),
            QualStatus::WarteAufGf => $this->user($qual->gf_id),
            QualStatus::Budgetpruefung => $this->personalabteilung(),
            default => null,
        };

        if ($empfaenger instanceof Model) {
            $empfaenger->notify(new FreigabeErforderlichNotification($qual));

            return;
        }

        if ($empfaenger instanceof Collection) {
            $empfaenger->each(fn (Model $user) => $user->notify(new FreigabeErforderlichNotification($qual)));
        }
    }

    public function entscheidung(Qual $qual, string $text): void
    {
        $this->beteiligte($qual)->each(
            fn (Model $user) => $user->notify(new EntscheidungNotification($qual, $text)),
        );
    }

    public function teilnehmer(Qual $qual, string $text): void
    {
        $teilnehmer = $qual->teilnehmer;
        if ($teilnehmer) {
            $teilnehmer->notify(new TeilnehmerAktionNotification($qual, $text));
        }
    }

    public function partner(Qual $qual, Model $partner, string $text): void
    {
        if ((int) $partner->getKey() === (int) $qual->teilnehmer_id) {
            return;
        }

        $partner->notify(new TeilnehmerAktionNotification($qual, $text));
    }

    public function nichtTeilgenommen(Qual $qual): void
    {
        $empfaenger = $this->personalabteilung();
        $vorgesetzter = $qual->teilnehmer?->vorgesetzter();

        if ($vorgesetzter) {
            $empfaenger->push($vorgesetzter);
        }

        $empfaenger
            ->unique(fn (Model $user): int => (int) $user->getKey())
            ->each(fn (Model $user) => $user->notify(new NichtTeilgenommenNotification($qual)));
    }

    /**
     * @return Collection<int, Model>
     */
    private function beteiligte(Qual $qual): Collection
    {
        return collect([$qual->teilnehmer, $qual->anleger])
            ->filter()
            ->unique(fn (Model $user): int => (int) $user->getKey())
            ->values();
    }

    private function user(?int $id): ?Model
    {
        if ($id === null) {
            return null;
        }

        return MaqualModels::userQuery()->find($id);
    }

    /**
     * @return Collection<int, Model>
     */
    private function personalabteilung(): Collection
    {
        return MaqualModels::userQuery()->permission('manage-app-maqual')->get();
    }
}
