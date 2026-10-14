<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppMaqual\Livewire;

use Hwkdo\IntranetAppMaqual\Models\Qual;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

class MeineQuals extends Component
{
    use WithPagination;

    #[Url]
    public string $filter = 'aktuell';

    #[Url]
    public string $suche = '';

    public function updatedFilter(): void
    {
        $this->resetPage();
    }

    public function updatedSuche(): void
    {
        $this->resetPage();
    }

    public function render(): View
    {
        $quals = Qual::query()
            ->with(['al', 'gf', 'teilnehmer'])
            ->where('teilnehmer_id', auth()->id())
            ->when($this->suche !== '', fn (Builder $query) => $query->where('name', 'like', '%'.$this->suche.'%'))
            ->when($this->filter === 'aktuell', fn (Builder $query) => $query->laufend())
            ->when($this->filter === 'abgeschlossen', fn (Builder $query) => $query->beendet())
            ->latest()
            ->paginate(20);

        return view('intranet-app-maqual::livewire.meine-quals', [
            'quals' => $quals,
            'darfAnlegen' => (bool) (auth()->user()?->ist_vorgesetzter ?? false) || auth()->user()?->can('manage-app-maqual'),
        ]);
    }

    /**
     * @return array<string, string>
     */
    public function filterOptionen(): array
    {
        return [
            'aktuell' => 'Aktuell',
            'abgeschlossen' => 'Abgeschlossen',
            'alle' => 'Alle',
        ];
    }
}
