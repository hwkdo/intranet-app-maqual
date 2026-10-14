<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppMaqual\Livewire;

use Hwkdo\IntranetAppMaqual\Models\Qual;
use Hwkdo\IntranetAppMaqual\Policies\QualPolicy;
use Hwkdo\IntranetAppMaqual\Support\MaqualModels;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Model;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

class MitarbeiterQuals extends Component
{
    use WithPagination;

    public Model $mitarbeiter;

    #[Url]
    public string $filter = 'aktuell';

    public function mount(int $mitarbeiter): void
    {
        $this->mitarbeiter = MaqualModels::userQuery()->findOrFail($mitarbeiter);
        $darf = auth()->user()?->can('manage-app-maqual')
            || app(QualPolicy::class)->create(auth()->user(), $this->mitarbeiter)
            || (int) auth()->id() === (int) $this->mitarbeiter->getKey();

        abort_unless($darf, 403);
    }

    public function updatedFilter(): void
    {
        $this->resetPage();
    }

    public function render(): View
    {
        $quals = Qual::query()
            ->with(['al', 'gf', 'teilnehmer'])
            ->where('teilnehmer_id', $this->mitarbeiter->getKey())
            ->when($this->filter === 'aktuell', fn ($query) => $query->laufend())
            ->when($this->filter === 'abgeschlossen', fn ($query) => $query->beendet())
            ->latest()
            ->paginate(20);

        return view('intranet-app-maqual::livewire.mitarbeiter-quals', [
            'quals' => $quals,
            'darfAnlegen' => app(QualPolicy::class)->create(auth()->user(), $this->mitarbeiter),
        ]);
    }
}
