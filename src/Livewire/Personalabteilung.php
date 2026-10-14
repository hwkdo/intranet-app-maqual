<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppMaqual\Livewire;

use Flux\Flux;
use Hwkdo\IntranetAppMaqual\Enums\QualStatus;
use Hwkdo\IntranetAppMaqual\Enums\Qualtyp;
use Hwkdo\IntranetAppMaqual\Exceptions\WorkflowException;
use Hwkdo\IntranetAppMaqual\Models\Qual;
use Hwkdo\IntranetAppMaqual\Services\QualWorkflow;
use Hwkdo\IntranetAppMaqual\Support\GbZuordnung;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

class Personalabteilung extends Component
{
    use WithPagination;

    #[Url]
    public string $kostenjahr = '';

    #[Url]
    public string $status = '';

    #[Url]
    public string $qualtyp = '';

    #[Url]
    public string $gbId = '';

    public string $budgetGvpId = '';

    public int $budgetJahr = 0;

    public string $budgetBetrag = '';

    public function mount(): void
    {
        $this->budgetJahr = (int) now()->year;
    }

    public function updatedKostenjahr(): void
    {
        $this->resetPage();
    }

    public function updatedStatus(): void
    {
        $this->resetPage();
    }

    public function updatedQualtyp(): void
    {
        $this->resetPage();
    }

    public function updatedGbId(): void
    {
        $this->resetPage();
    }

    public function budgetSpeichern(QualWorkflow $workflow): void
    {
        $this->validate([
            'budgetGvpId' => ['required', 'integer'],
            'budgetJahr' => ['required', 'integer', 'min:2000', 'max:2100'],
            'budgetBetrag' => ['required', 'numeric', 'min:0'],
        ]);

        try {
            $workflow->budgetSetzen(auth()->user(), (int) $this->budgetGvpId, $this->budgetJahr, $this->budgetBetrag);
        } catch (WorkflowException $exception) {
            $this->addError('budgetBetrag', $exception->getMessage());

            return;
        }

        Flux::toast(text: 'Budget wurde gespeichert.', variant: 'success');
        $this->reset('budgetBetrag');
    }

    public function render(): View
    {
        $gb = app(GbZuordnung::class);
        $mitarbeiterIds = $this->gbId !== '' ? $gb->mitarbeiterIds((int) $this->gbId) : null;

        $quals = Qual::query()
            ->with(['teilnehmer', 'al', 'gf'])
            ->when($this->kostenjahr !== '', fn ($query) => $query->where('kostenjahr', (int) $this->kostenjahr))
            ->when($this->status !== '', fn ($query) => $query->where('status', $this->status))
            ->when($this->qualtyp !== '', fn ($query) => $query->where('qualtyp', $this->qualtyp))
            ->when($mitarbeiterIds !== null, fn ($query) => $query->whereIn('teilnehmer_id', $mitarbeiterIds === [] ? [0] : $mitarbeiterIds))
            ->latest()
            ->paginate(25);

        return view('intranet-app-maqual::livewire.personalabteilung', [
            'quals' => $quals,
            'geschaeftsbereiche' => $gb->budgetGvps(),
            'statusOptionen' => QualStatus::cases(),
            'typen' => Qualtyp::cases(),
        ]);
    }
}
