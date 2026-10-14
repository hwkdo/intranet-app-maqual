<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppMaqual\Policies;

use Hwkdo\IntranetAppMaqual\Enums\QualStatus;
use Hwkdo\IntranetAppMaqual\Models\Qual;
use Hwkdo\IntranetAppMaqual\Support\FreigabeKetteResolver;
use Illuminate\Database\Eloquent\Model;

class QualPolicy
{
    public function __construct(
        private readonly FreigabeKetteResolver $kette,
    ) {}

    public function view(Model $user, Qual $qual): bool
    {
        if ($user->can('manage-app-maqual')) {
            return true;
        }

        if ((int) $user->getKey() === (int) $qual->teilnehmer_id) {
            return true;
        }

        return $this->fuehrt($user, (int) $qual->teilnehmer_id);
    }

    public function create(Model $user, Model $teilnehmer): bool
    {
        if ($user->can('manage-app-maqual')) {
            return true;
        }

        if ((int) $user->getKey() === (int) $teilnehmer->getKey()) {
            return false;
        }

        return $this->fuehrt($user, (int) $teilnehmer->getKey());
    }

    public function alsAl(Model $user, Qual $qual): bool
    {
        return $qual->status === QualStatus::Neu
            && $this->kette->kannStufe($user, 'al', $qual->al_id);
    }

    public function alsGf(Model $user, Qual $qual): bool
    {
        return $qual->status === QualStatus::WarteAufGf
            && $this->kette->kannStufe($user, 'gf', $qual->gf_id);
    }

    public function alsPersonal(Model $user, Qual $qual): bool
    {
        return $user->can('manage-app-maqual')
            && $qual->status === QualStatus::Budgetpruefung;
    }

    public function korrigieren(Model $user, Qual $qual): bool
    {
        return $user->can('manage-app-maqual');
    }

    public function anmeldung(Model $user, Qual $qual): bool
    {
        return $qual->status === QualStatus::Genehmigt
            && $this->istTeilnehmerOderPersonal($user, $qual);
    }

    public function teilnahme(Model $user, Qual $qual): bool
    {
        return $qual->teilnahmeFällig()
            && $this->istTeilnehmerOderPersonal($user, $qual);
    }

    public function datumSetzen(Model $user, Qual $qual): bool
    {
        if (! in_array($qual->status, [QualStatus::Genehmigt, QualStatus::AnmeldungBestaetigt], true)) {
            return false;
        }

        return $this->istTeilnehmerOderPersonal($user, $qual)
            || $this->fuehrt($user, (int) $qual->teilnehmer_id);
    }

    public function nichtTeilgenommen(Model $user, Qual $qual): bool
    {
        return in_array($qual->status, [QualStatus::Genehmigt, QualStatus::AnmeldungBestaetigt], true)
            && $this->istTeilnehmerOderPersonal($user, $qual);
    }

    public function bewerten(Model $user, Qual $qual): bool
    {
        return $qual->status === QualStatus::TeilnahmeBestaetigt
            && (int) $user->getKey() === (int) $qual->teilnehmer_id;
    }

    public function evaluieren(Model $user, Qual $qual): bool
    {
        if ($qual->status !== QualStatus::BewertungErfolgt) {
            return false;
        }

        if ((int) $user->getKey() === (int) $qual->teilnehmer_id) {
            return true;
        }

        $partner = $qual->teilnehmer?->vorgesetzter();

        return $partner !== null && (int) $partner->getKey() === (int) $user->getKey();
    }

    private function istTeilnehmerOderPersonal(Model $user, Qual $qual): bool
    {
        return $user->can('manage-app-maqual')
            || (int) $user->getKey() === (int) $qual->teilnehmer_id;
    }

    private function fuehrt(Model $user, int $teilnehmerId): bool
    {
        if (! ($user->ist_vorgesetzter ?? false)) {
            return false;
        }

        $untergebene = $user->getUntergebene(true);
        if ($untergebene === false) {
            return false;
        }

        return $untergebene->contains(
            fn (Model $mitarbeiter): bool => (int) $mitarbeiter->getKey() === $teilnehmerId,
        );
    }
}
