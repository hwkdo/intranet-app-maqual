<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppMaqual\Livewire;

use Flux\Flux;
use Hwkdo\IntranetAppMaqual\Enums\QualQuelle;
use Hwkdo\IntranetAppMaqual\Enums\Qualtyp;
use Hwkdo\IntranetAppMaqual\Exceptions\WorkflowException;
use Hwkdo\IntranetAppMaqual\Models\Qual;
use Hwkdo\IntranetAppMaqual\Policies\QualPolicy;
use Hwkdo\IntranetAppMaqual\Services\Fragebogen;
use Hwkdo\IntranetAppMaqual\Services\QualWorkflow;
use Hwkdo\IntranetAppMaqual\Support\Geld;
use Illuminate\Contracts\View\View;
use Livewire\Component;
use Livewire\WithFileUploads;

class QualShow extends Component
{
    use WithFileUploads;

    public Qual $qual;

    public string $ablehnung = '';

    public string $neueKosten = '';

    public string $kostenGrund = '';

    public string $gfEntscheidung = 'annehmen';

    public $anmeldePdf;

    public $zertifikatPdf;

    public string $kostenTatsaechlich = '';

    public ?string $datumBeginn = null;

    public string $nichtTeilgenommenGrund = '';

    /** @var array<string, mixed> */
    public array $bewertung = [];

    /** @var array<string, mixed> */
    public array $evaluierung = [];

    public string $name = '';

    public string $beschreibung = '';

    public string $begruendung = '';

    public string $qualtyp = '';

    public int $kostenjahr = 0;

    public bool $pflicht = false;

    public bool $kostenlos = false;

    public string $kostenVoraussichtlich = '';

    public string $korrekturKostenTatsaechlich = '';

    public string $quelle = '';

    public $ersatzAnmeldung;

    public $ersatzZertifikat;

    public function mount(Qual $qual): void
    {
        $this->qual = $qual->load(['teilnehmer', 'anleger', 'gl', 'al', 'gf', 'glEntschiedenVon', 'alEntschiedenVon', 'gfEntschiedenVon', 'bewertung', 'evaluierung', 'aktivitaeten.user']);
        abort_unless(app(QualPolicy::class)->view(auth()->user(), $this->qual), 403);
        $this->formularFuellen();
    }

    public function alZustimmen(QualWorkflow $workflow): void
    {
        $this->aktion(fn () => $workflow->alZustimmen($this->qual, auth()->user()), 'Zustimmung wurde gespeichert.');
    }

    public function alAblehnen(QualWorkflow $workflow): void
    {
        $this->validate(['ablehnung' => ['required', 'string', 'min:3']]);
        $this->aktion(fn () => $workflow->alAblehnen($this->qual, auth()->user(), $this->ablehnung), 'Qualifizierung wurde abgelehnt.');
    }

    public function gfEntscheidungSpeichern(QualWorkflow $workflow): void
    {
        if ($this->gfEntscheidung === 'ablehnen') {
            $this->gfAblehnen($workflow);

            return;
        }

        if ($this->gfEntscheidung === 'aendern') {
            $this->neueKosten = Geld::normalisieren($this->neueKosten);
            $this->validate([
                'neueKosten' => ['required', 'numeric', 'min:0'],
                'kostenGrund' => ['required', 'string', 'min:3'],
            ], [
                'neueKosten.required' => 'Bitte den neuen Betrag eintragen.',
                'kostenGrund.required' => 'Bitte begründen, warum die Kosten geändert werden.',
                'kostenGrund.min' => 'Die Begründung muss mindestens 3 Zeichen haben.',
            ]);

            $beantragt = Geld::normalisieren((string) $this->qual->kosten_voraussichtlich);
            if ($this->neueKosten === $beantragt) {
                $this->addError('neueKosten', 'Dieser Betrag entspricht den beantragten Kosten. Wählen Sie „Annehmen“, wenn die Kosten bleiben sollen.');

                return;
            }

            $this->aktion(
                fn () => $workflow->gfZustimmen($this->qual, auth()->user(), $this->neueKosten, $this->kostenGrund),
                'Zustimmung mit geänderten Kosten wurde gespeichert.',
            );

            return;
        }

        $this->aktion(
            fn () => $workflow->gfZustimmen($this->qual, auth()->user()),
            'Zustimmung wurde gespeichert.',
        );
    }

    public function gfAblehnen(QualWorkflow $workflow): void
    {
        $this->validate(['ablehnung' => ['required', 'string', 'min:3']]);
        $this->aktion(fn () => $workflow->gfAblehnen($this->qual, auth()->user(), $this->ablehnung), 'Qualifizierung wurde abgelehnt.');
    }

    public function persoGenehmigen(QualWorkflow $workflow): void
    {
        $this->aktion(fn () => $workflow->persoGenehmigen($this->qual, auth()->user()), 'Budgetprüfung wurde genehmigt.');
    }

    public function persoAblehnen(QualWorkflow $workflow): void
    {
        $this->validate(['ablehnung' => ['required', 'string', 'min:3']]);
        $this->aktion(fn () => $workflow->persoAblehnen($this->qual, auth()->user(), $this->ablehnung), 'Budgetprüfung wurde abgelehnt.');
    }

    public function abbrechen(QualWorkflow $workflow): void
    {
        $this->validate(['ablehnung' => ['required', 'string', 'min:3']]);
        $this->aktion(fn () => $workflow->abbrechen($this->qual, auth()->user(), $this->ablehnung), 'Workflow wurde abgebrochen.');
    }

    public function anmeldungSpeichern(QualWorkflow $workflow): void
    {
        $this->validate(['anmeldePdf' => ['required', 'file', 'mimes:pdf', 'max:10240']]);
        $this->aktion(fn () => $workflow->anmeldebestatigung($this->qual, auth()->user(), $this->anmeldePdf), 'Anmeldebestätigung wurde gespeichert.');
    }

    public function anmeldePdfEntfernen(): void
    {
        $this->dateiEntfernen('anmeldePdf');
    }

    public function zertifikatPdfEntfernen(): void
    {
        $this->dateiEntfernen('zertifikatPdf');
    }

    public function ersatzAnmeldungEntfernen(): void
    {
        $this->dateiEntfernen('ersatzAnmeldung');
    }

    public function ersatzZertifikatEntfernen(): void
    {
        $this->dateiEntfernen('ersatzZertifikat');
    }

    public function nichtTeilgenommen(QualWorkflow $workflow): void
    {
        $this->validate(['nichtTeilgenommenGrund' => ['required', 'string', 'min:3']]);
        $this->aktion(fn () => $workflow->nichtTeilgenommen($this->qual, auth()->user(), $this->nichtTeilgenommenGrund), 'Teilnahme wurde beendet.');
    }

    public function datumSpeichern(QualWorkflow $workflow): void
    {
        $this->validate(['datumBeginn' => ['nullable', 'date']]);
        $this->aktion(fn () => $workflow->datumSetzen($this->qual, auth()->user(), $this->datumBeginn), 'Beginndatum wurde gespeichert.');
    }

    public function teilnahmeSpeichern(QualWorkflow $workflow): void
    {
        $this->validate([
            'zertifikatPdf' => ['required', 'file', 'mimes:pdf', 'max:10240'],
            'kostenTatsaechlich' => ['required', 'numeric', 'min:0'],
        ]);
        $this->aktion(
            fn () => $workflow->teilnahmeBestaetigen($this->qual, auth()->user(), $this->zertifikatPdf, $this->kostenTatsaechlich),
            'Teilnahme wurde bestätigt.',
        );
    }

    public function bewertungSpeichern(QualWorkflow $workflow): void
    {
        $this->validate(
            Fragebogen::regeln('bewertung', 'bewertung'),
            Fragebogen::meldungen('bewertung', 'bewertung'),
        );
        $this->aktion(fn () => $workflow->bewertungSpeichern($this->qual, auth()->user(), $this->bewertung), 'Bewertung wurde gespeichert.');
    }

    public function evaluierungSpeichern(QualWorkflow $workflow): void
    {
        $this->validate(
            Fragebogen::regeln('evaluierung', 'evaluierung'),
            Fragebogen::meldungen('evaluierung', 'evaluierung'),
        );
        $this->aktion(fn () => $workflow->evaluierungBestaetigen($this->qual, auth()->user(), $this->evaluierung), 'Evaluierung wurde gespeichert.');
    }

    public function korrekturSpeichern(QualWorkflow $workflow): void
    {
        if ($this->kostenlos) {
            $this->kostenVoraussichtlich = '0';
        }

        $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'beschreibung' => ['nullable', 'string'],
            'begruendung' => ['required', 'string'],
            'qualtyp' => ['required', 'in:fachbezogen,paedagogisch'],
            'kostenjahr' => ['required', 'integer'],
            'pflicht' => ['boolean'],
            'kostenlos' => ['boolean'],
            'kostenVoraussichtlich' => ['required', 'numeric', 'min:0'],
            'korrekturKostenTatsaechlich' => ['nullable', 'numeric', 'min:0'],
            'datumBeginn' => ['nullable', 'date'],
            'quelle' => ['required', 'in:manuell,magespraech'],
        ]);

        $this->aktion(fn () => $workflow->korrigieren($this->qual, auth()->user(), [
            'name' => $this->name,
            'beschreibung' => $this->beschreibung,
            'begruendung' => $this->begruendung,
            'qualtyp' => $this->qualtyp,
            'kostenjahr' => $this->kostenjahr,
            'pflicht' => $this->pflicht,
            'kostenlos' => $this->kostenlos,
            'kosten_voraussichtlich' => $this->kostenVoraussichtlich,
            'kosten_tatsaechlich' => $this->korrekturKostenTatsaechlich !== '' ? $this->korrekturKostenTatsaechlich : null,
            'datum_beginn' => $this->datumBeginn,
            'quelle' => $this->quelle,
        ]), 'Änderungen wurden protokolliert.');
    }

    public function anmeldungErsetzen(QualWorkflow $workflow): void
    {
        $this->validate(['ersatzAnmeldung' => ['required', 'file', 'mimes:pdf', 'max:10240']]);
        $this->aktion(fn () => $workflow->dateiErsetzen($this->qual, auth()->user(), 'anmeldebestatigung', $this->ersatzAnmeldung), 'Anmeldebestätigung wurde ausgetauscht.');
    }

    public function zertifikatErsetzen(QualWorkflow $workflow): void
    {
        $this->validate(['ersatzZertifikat' => ['required', 'file', 'mimes:pdf', 'max:10240']]);
        $this->aktion(fn () => $workflow->dateiErsetzen($this->qual, auth()->user(), 'zertifikat', $this->ersatzZertifikat), 'Zertifikat wurde ausgetauscht.');
    }

    private function dateiEntfernen(string $feld): void
    {
        if (is_object($this->{$feld}) && method_exists($this->{$feld}, 'delete')) {
            $this->{$feld}->delete();
        }

        $this->{$feld} = null;
    }

    public function render(): View
    {
        $policy = app(QualPolicy::class);
        $user = auth()->user();

        return view('intranet-app-maqual::livewire.qual-show', [
            'policy' => $policy,
            'user' => $user,
            'bewertungFragen' => Fragebogen::bewertung(),
            'evalFragen' => Fragebogen::evaluierung(),
            'noten' => Fragebogen::noten(),
            'typen' => Qualtyp::cases(),
            'quellen' => QualQuelle::cases(),
        ]);
    }

    private function aktion(callable $callback, string $erfolg): void
    {
        try {
            $callback();
        } catch (WorkflowException $exception) {
            $this->addError('aktion', $exception->getMessage());

            return;
        }

        $this->qual->refresh()->load(['teilnehmer', 'anleger', 'gl', 'al', 'gf', 'glEntschiedenVon', 'alEntschiedenVon', 'gfEntschiedenVon', 'bewertung', 'evaluierung', 'aktivitaeten.user']);
        $this->formularFuellen();
        $this->reset('anmeldePdf', 'zertifikatPdf', 'ersatzAnmeldung', 'ersatzZertifikat', 'ablehnung');
        Flux::toast(text: $erfolg, variant: 'success');
    }

    private function formularFuellen(): void
    {
        $this->datumBeginn = $this->qual->datum_beginn?->toDateString();
        $this->name = $this->qual->name;
        $this->beschreibung = (string) $this->qual->beschreibung;
        $this->begruendung = $this->qual->begruendung;
        $this->qualtyp = $this->qual->qualtyp->value;
        $this->kostenjahr = (int) $this->qual->kostenjahr;
        $this->pflicht = (bool) $this->qual->pflicht;
        $this->kostenlos = (bool) $this->qual->kostenlos;
        $this->kostenVoraussichtlich = (string) $this->qual->kosten_voraussichtlich;
        $this->korrekturKostenTatsaechlich = (string) ($this->qual->kosten_tatsaechlich ?? '');
        $this->quelle = $this->qual->quelle->value;
        $this->neueKosten = Geld::eingabe($this->qual->kosten_voraussichtlich);
        $this->bewertung = Fragebogen::formular('bewertung', $this->qual->bewertung?->data ?? []);
        $this->evaluierung = Fragebogen::formular('evaluierung', $this->qual->evaluierung?->data ?? []);
    }
}
