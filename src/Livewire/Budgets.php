<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppMaqual\Livewire;

use Hwkdo\IntranetAppMaqual\Services\BudgetAuswertung;
use Hwkdo\IntranetAppMaqual\Support\GbZuordnung;
use Hwkdo\IntranetAppMaqual\Support\MaqualModels;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Url;
use Livewire\Component;

class Budgets extends Component
{
    #[Url]
    public int $kostenjahr = 0;

    public function mount(): void
    {
        if ($this->kostenjahr < 2000) {
            $this->kostenjahr = (int) now()->year;
        }

        abort_unless(app(GbZuordnung::class)->gefuehrteGbIds(auth()->user()) !== [], 403);
    }

    public function render(): View
    {
        $ids = app(GbZuordnung::class)->gefuehrteGbIds(auth()->user());
        $auswertung = app(BudgetAuswertung::class);
        $bereiche = MaqualModels::gvpQuery()->whereIn('id', $ids)->orderBy('name')->get();

        $bloecke = $bereiche->map(function ($gvp) use ($auswertung) {
            return [
                'gvp' => $gvp,
                'zahlen' => $auswertung->fuerGb((int) $gvp->getKey(), $this->kostenjahr),
            ];
        });

        return view('intranet-app-maqual::livewire.budgets', [
            'bloecke' => $bloecke,
            'jahre' => [(int) now()->year - 1, (int) now()->year, (int) now()->year + 1],
        ]);
    }
}
