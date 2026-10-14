<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppMaqual\Livewire;

use Hwkdo\IntranetAppMaqual\Models\Qual;
use Illuminate\Contracts\View\View;
use Livewire\Component;

class MeineMitarbeiter extends Component
{
    public function mount(): void
    {
        abort_unless((bool) (auth()->user()?->ist_vorgesetzter ?? false) || auth()->user()?->can('manage-app-maqual'), 403);
    }

    public function render(): View
    {
        $untergebene = auth()->user()?->getUntergebene(true);
        $mitarbeiter = $untergebene === false || $untergebene === null
            ? collect()
            : $untergebene->sortBy(fn ($user) => $user->nachname.' '.$user->vorname)->values();

        $ids = $mitarbeiter->pluck('id');
        $anzahlen = Qual::query()
            ->selectRaw('teilnehmer_id, count(*) as anzahl')
            ->whereIn('teilnehmer_id', $ids->isEmpty() ? [0] : $ids)
            ->groupBy('teilnehmer_id')
            ->pluck('anzahl', 'teilnehmer_id');

        return view('intranet-app-maqual::livewire.meine-mitarbeiter', [
            'mitarbeiter' => $mitarbeiter,
            'anzahlen' => $anzahlen,
        ]);
    }
}
