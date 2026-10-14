<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppMaqual\Tasks;

use Hwkdo\IntranetAppBase\Data\TaskItem;
use Hwkdo\IntranetAppBase\Interfaces\TaskProviderInterface;
use Hwkdo\IntranetAppMaqual\Enums\QualStatus;
use Hwkdo\IntranetAppMaqual\IntranetAppMaqual;
use Hwkdo\IntranetAppMaqual\Models\Qual;
use Hwkdo\IntranetAppMaqual\Policies\QualPolicy;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

class OffeneQualAktionenTaskProvider implements TaskProviderInterface
{
    public function __construct(
        private readonly QualPolicy $policy,
    ) {}

    public function getLabel(): string
    {
        return 'Mitarbeiterqualifizierungen';
    }

    public function getTasksForUser(Authenticatable $user): Collection
    {
        if (! $user instanceof Model) {
            return collect();
        }

        return Qual::query()
            ->with(['teilnehmer', 'evaluierung'])
            ->whereNotIn('status', QualStatus::beendetValues())
            ->latest()
            ->limit(200)
            ->get()
            ->filter(fn (Qual $qual): bool => $this->istOffenFuer($user, $qual))
            ->take(50)
            ->map(fn (Qual $qual): TaskItem => new TaskItem(
                title: $qual->name,
                url: route('apps.maqual.quals.show', $qual),
                appIdentifier: IntranetAppMaqual::identifier(),
                appName: IntranetAppMaqual::app_name(),
                appIcon: IntranetAppMaqual::app_icon(),
                description: $qual->teilnehmer?->name,
                badge: $qual->status->label(),
                priority: 40,
            ))
            ->values();
    }

    private function istOffenFuer(Model $user, Qual $qual): bool
    {
        return $this->policy->alsAl($user, $qual)
            || $this->policy->alsGf($user, $qual)
            || $this->policy->alsPersonal($user, $qual)
            || $this->policy->anmeldung($user, $qual)
            || $this->policy->teilnahme($user, $qual)
            || $this->policy->bewerten($user, $qual)
            || $this->evaluierungOffen($user, $qual);
    }

    private function evaluierungOffen(Model $user, Qual $qual): bool
    {
        if (! $this->policy->evaluieren($user, $qual)) {
            return false;
        }

        $evaluierung = $qual->evaluierung;
        if ($evaluierung === null) {
            return true;
        }

        $istTeilnehmer = (int) $user->getKey() === (int) $qual->teilnehmer_id;

        return $istTeilnehmer
            ? $evaluierung->teilnehmer_bestaetigt_am === null
            : $evaluierung->vorgesetzter_bestaetigt_am === null;
    }
}
