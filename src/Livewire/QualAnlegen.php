<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppMaqual\Livewire;

use Flux\Flux;
use Hwkdo\IntranetAppMaqual\Enums\Qualtyp;
use Hwkdo\IntranetAppMaqual\Exceptions\WorkflowException;
use Hwkdo\IntranetAppMaqual\Services\QualWorkflow;
use Hwkdo\IntranetAppMaqual\Support\MaqualModels;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Livewire\Component;

class QualAnlegen extends Component
{
    public ?int $mitarbeiterId = null;

    public string $name = '';

    public string $beschreibung = '';

    public string $begruendung = '';

    public string $qualtyp = 'fachbezogen';

    public int $kostenjahr = 0;

    public bool $pflicht = false;

    public bool $kostenlos = false;

    /** @var list<string> */
    public array $stellvertretend = [];

    public string $kostenVoraussichtlich = '';

    public ?string $datumBeginn = null;

    public function mount(): void
    {
        $id = request()->integer('mitarbeiter');
        $this->mitarbeiterId = $id > 0 ? $id : null;
        $this->kostenjahr = (int) now()->year;

        abort_unless($this->darfAnlegen(), 403);
    }

    public function updatedMitarbeiterId(): void
    {
        $this->stellvertretend = [];
    }

    public function save(QualWorkflow $workflow): void
    {
        if ($this->kostenlos) {
            $this->kostenVoraussichtlich = '0';
        }

        $this->validate([
            'mitarbeiterId' => ['required', 'integer'],
            'name' => ['required', 'string', 'max:255'],
            'beschreibung' => ['nullable', 'string', 'max:5000'],
            'begruendung' => ['required', 'string', 'max:5000'],
            'qualtyp' => ['required', 'in:fachbezogen,paedagogisch'],
            'kostenjahr' => ['required', 'integer', 'in:'.now()->year.','.(now()->year + 1)],
            'pflicht' => ['boolean'],
            'kostenlos' => ['boolean'],
            'kostenVoraussichtlich' => ['required', 'numeric', 'min:0'],
            'datumBeginn' => ['nullable', 'date'],
        ]);

        $teilnehmer = MaqualModels::userQuery()->findOrFail($this->mitarbeiterId);

        try {
            $qual = $workflow->anlegen(auth()->user(), $teilnehmer, [
                'name' => $this->name,
                'beschreibung' => $this->beschreibung !== '' ? $this->beschreibung : null,
                'begruendung' => $this->begruendung,
                'qualtyp' => Qualtyp::from($this->qualtyp),
                'kostenjahr' => $this->kostenjahr,
                'pflicht' => $this->pflicht,
                'kostenlos' => $this->kostenlos,
                'kosten_voraussichtlich' => $this->kostenVoraussichtlich,
                'datum_beginn' => $this->datumBeginn,
                'stellvertretend' => $this->stellvertretend,
            ]);
        } catch (WorkflowException $exception) {
            $this->addError('anlegen', $exception->getMessage());

            return;
        }

        Flux::toast(text: 'Qualifizierung wurde angelegt.', variant: 'success');
        $this->redirect(route('apps.maqual.quals.show', $qual), navigate: true);
    }

    public function render(): View
    {
        return view('intranet-app-maqual::livewire.qual-anlegen', [
            'mitarbeiter' => $this->mitarbeiterOptionen(),
            'jahre' => [(int) now()->year, (int) now()->year + 1],
            'typen' => Qualtyp::cases(),
            'stellvertretungen' => $this->stellvertretungsOptionen(),
        ]);
    }

    /**
     * @return Collection<int, Model>
     */
    private function mitarbeiterOptionen(): Collection
    {
        $user = auth()->user();
        if ($user?->can('manage-app-maqual')) {
            $liste = MaqualModels::userQuery()
                ->where('active', true)
                ->orderBy('nachname')
                ->orderBy('vorname')
                ->get();
        } else {
            $untergebene = $user?->getUntergebene(true);
            $liste = $untergebene === false || $untergebene === null
                ? collect()
                : $untergebene;
        }

        $ausgewaehlt = $this->mitarbeiterId
            ? MaqualModels::userQuery()->find($this->mitarbeiterId)
            : null;

        if ($ausgewaehlt && ! $liste->contains(fn (Model $eintrag): bool => (int) $eintrag->getKey() === (int) $ausgewaehlt->getKey())) {
            $liste->push($ausgewaehlt);
        }

        return $liste
            ->sortBy(fn (Model $person): string => mb_strtolower(trim((string) $person->nachname.' '.$person->vorname)))
            ->values();
    }

    private function darfAnlegen(): bool
    {
        $user = auth()->user();

        return (bool) ($user?->ist_vorgesetzter ?? false) || (bool) $user?->can('manage-app-maqual');
    }

    /**
     * @return list<array{stufe: string, label: string}>
     */
    private function stellvertretungsOptionen(): array
    {
        $user = auth()->user();
        if ($user === null || $this->mitarbeiterId === null) {
            return [];
        }

        $teilnehmer = MaqualModels::userQuery()->find($this->mitarbeiterId);
        if ($teilnehmer === null) {
            return [];
        }

        $texte = [
            'gl' => 'Mit dem Anlegen auch als Stellvertretung der Gruppenleitung zustimmen',
            'al' => 'Mit dem Anlegen auch als Stellvertretung der Abteilungsleitung zustimmen',
            'gf' => 'Mit dem Anlegen auch als Stellvertretung der Geschäftsführung zustimmen',
        ];

        return collect(app(QualWorkflow::class)->moeglicheStellvertretungen($user, $teilnehmer))
            ->map(fn (string $stufe): array => [
                'stufe' => $stufe,
                'label' => $texte[$stufe],
            ])
            ->values()
            ->all();
    }
}
