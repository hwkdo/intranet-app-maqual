<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppMaqual\Services;

use Hwkdo\IntranetAppMaqual\Enums\AktivitaetTyp;
use Hwkdo\IntranetAppMaqual\Enums\QualQuelle;
use Hwkdo\IntranetAppMaqual\Enums\QualStatus;
use Hwkdo\IntranetAppMaqual\Enums\Qualtyp;
use Hwkdo\IntranetAppMaqual\Evaluierung\EvaluierungProviderResolver;
use Hwkdo\IntranetAppMaqual\Exceptions\WorkflowException;
use Hwkdo\IntranetAppMaqual\Models\Budget;
use Hwkdo\IntranetAppMaqual\Models\Evaluierung;
use Hwkdo\IntranetAppMaqual\Models\Qual;
use Hwkdo\IntranetAppMaqual\Policies\QualPolicy;
use Hwkdo\IntranetAppMaqual\Support\FreigabeKette;
use Hwkdo\IntranetAppMaqual\Support\FreigabeKetteResolver;
use Hwkdo\IntranetAppMaqual\Support\MaqualModels;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

class QualWorkflow
{
    /**
     * @var list<string>
     */
    private const KORREKTUR_FELDER = [
        'name',
        'beschreibung',
        'begruendung',
        'qualtyp',
        'kostenjahr',
        'pflicht',
        'kostenlos',
        'kosten_voraussichtlich',
        'kosten_tatsaechlich',
        'datum_beginn',
        'quelle',
    ];

    public function __construct(
        private readonly FreigabeKetteResolver $ketteResolver,
        private readonly AktivitaetLogger $logger,
        private readonly QualBenachrichtigungen $benachrichtigungen,
        private readonly QualPolicy $policy,
        private readonly EvaluierungProviderResolver $evaluierung,
    ) {}

    /**
     * @param  array{
     *     name: string,
     *     beschreibung?: ?string,
     *     begruendung: string,
     *     qualtyp: Qualtyp|string,
     *     kostenjahr: int,
     *     pflicht: bool,
     *     kostenlos?: bool,
     *     kosten_voraussichtlich: float|string,
     *     datum_beginn?: ?string,
     *     stellvertretend?: list<string>
     * }  $daten
     */
    public function anlegen(Model $anleger, Model $teilnehmer, array $daten): Qual
    {
        if (! $this->policy->create($anleger, $teilnehmer)) {
            throw new WorkflowException('Sie dürfen für diesen Mitarbeiter keine Qualifizierung anlegen.');
        }

        $jahr = (int) $daten['kostenjahr'];
        $aktuell = (int) now()->year;
        if (! in_array($jahr, [$aktuell, $aktuell + 1], true)) {
            throw new WorkflowException('Das Kostenjahr muss das aktuelle Jahr oder das Folgejahr sein.');
        }

        $kette = $this->ketteResolver->fuerTeilnehmer($teilnehmer);
        $flags = $this->entscheidungenFuerAnlage($anleger, $teilnehmer, $kette, $daten['stellvertretend'] ?? []);
        $status = $this->anfangsStatus(array_map(fn (array $flag): ?bool => $flag['status'], $flags), $kette);
        $kostenlos = (bool) ($daten['kostenlos'] ?? false);
        $bemerkung = $this->anlageBemerkung($flags);

        $qual = DB::transaction(function () use ($anleger, $teilnehmer, $daten, $kette, $flags, $status, $jahr, $kostenlos, $bemerkung): Qual {
            $qual = Qual::query()->create([
                'teilnehmer_id' => $teilnehmer->getKey(),
                'angelegt_von_id' => $anleger->getKey(),
                'name' => $daten['name'],
                'beschreibung' => $daten['beschreibung'] ?? null,
                'begruendung' => $daten['begruendung'],
                'quelle' => QualQuelle::Manuell,
                'status' => $status,
                'qualtyp' => $daten['qualtyp'] instanceof Qualtyp ? $daten['qualtyp'] : Qualtyp::from((string) $daten['qualtyp']),
                'kostenjahr' => $jahr,
                'pflicht' => (bool) $daten['pflicht'],
                'kostenlos' => $kostenlos,
                'kosten_voraussichtlich' => $kostenlos ? '0.00' : $daten['kosten_voraussichtlich'],
                'datum_beginn' => $daten['datum_beginn'] ?? null,
                'gl_id' => $kette->glId,
                'al_id' => $kette->alId,
                'gf_id' => $kette->gfId,
                'gl_status' => $flags['gl']['status'],
                'al_status' => $flags['al']['status'],
                'gf_status' => $flags['gf']['status'],
                'gl_entschieden_von_id' => $flags['gl']['von'],
                'al_entschieden_von_id' => $flags['al']['von'],
                'gf_entschieden_von_id' => $flags['gf']['von'],
                'gl_stellvertretung' => $flags['gl']['stellvertretung'],
                'al_stellvertretung' => $flags['al']['stellvertretung'],
                'gf_stellvertretung' => $flags['gf']['stellvertretung'],
                'evaluierung_provider' => 'in_app',
            ]);

            $this->logger->status($qual, $anleger, null, $status, $bemerkung);

            return $qual;
        });

        $qual->load('teilnehmer', 'anleger');
        $this->benachrichtigungen->naechsteFreigabe($qual);

        return $qual;
    }

    public function alZustimmen(Qual $qual, Model $user): Qual
    {
        $this->assert($this->policy->alsAl($user, $qual), 'Diese Qualifizierung wartet nicht auf Ihre Zustimmung als Abteilungsleitung.');

        return $this->wechseln($qual, $user, QualStatus::WarteAufGf, function (Qual $qual) use ($user): void {
            $this->entscheidungFesthalten($qual, $user, 'al', true);
            $qual->save();
            $this->benachrichtigungen->naechsteFreigabe($qual->fresh(['teilnehmer', 'anleger']));
        });
    }

    public function alAblehnen(Qual $qual, Model $user, string $begruendung): Qual
    {
        $this->assert($this->policy->alsAl($user, $qual), 'Diese Qualifizierung wartet nicht auf Ihre Zustimmung als Abteilungsleitung.');
        $this->begruendungPflicht($begruendung);

        return $this->wechseln($qual, $user, QualStatus::Abgelehnt, function (Qual $qual) use ($user, $begruendung): void {
            $this->entscheidungFesthalten($qual, $user, 'al', false);
            $qual->entscheidung_begruendung = $begruendung;
            $qual->save();
        }, $begruendung, true);
    }

    public function gfZustimmen(Qual $qual, Model $user, ?string $neueKosten = null, ?string $kostenGrund = null): Qual
    {
        $this->assert($this->policy->alsGf($user, $qual), 'Diese Qualifizierung wartet nicht auf die Geschäftsführung.');

        return DB::transaction(function () use ($qual, $user, $neueKosten, $kostenGrund): Qual {
            $von = $qual->status;
            if ($neueKosten !== null && $neueKosten !== '') {
                $this->kostenAendern($qual, $user, 'kosten_voraussichtlich', $neueKosten, $kostenGrund);
            }

            $this->entscheidungFesthalten($qual, $user, 'gf', true);
            $qual->status = QualStatus::Budgetpruefung;
            $qual->save();
            $this->logger->status($qual, $user, $von, QualStatus::Budgetpruefung);

            $frisch = $qual->fresh(['teilnehmer', 'anleger']);
            $this->benachrichtigungen->naechsteFreigabe($frisch);
            $this->benachrichtigungen->entscheidung($frisch, '„'.$qual->name.'“ liegt zur Budgetprüfung bei der Personalabteilung.');

            return $frisch;
        });
    }

    public function gfAblehnen(Qual $qual, Model $user, string $begruendung): Qual
    {
        $this->assert($this->policy->alsGf($user, $qual), 'Diese Qualifizierung wartet nicht auf die Geschäftsführung.');
        $this->begruendungPflicht($begruendung);

        return $this->wechseln($qual, $user, QualStatus::Abgelehnt, function (Qual $qual) use ($user, $begruendung): void {
            $this->entscheidungFesthalten($qual, $user, 'gf', false);
            $qual->entscheidung_begruendung = $begruendung;
            $qual->save();
        }, $begruendung, true);
    }

    public function persoGenehmigen(Qual $qual, Model $user): Qual
    {
        $this->assert($this->policy->alsPersonal($user, $qual), 'Die Budgetprüfung ist nicht offen.');

        return $this->wechseln($qual, $user, QualStatus::Genehmigt, function (Qual $qual): void {
            $qual->personalabteilung_status = true;
            $qual->save();
        }, benachrichtigeTeilnehmer: 'Bitte laden Sie die Anmeldebestätigung als PDF hoch.');
    }

    public function persoAblehnen(Qual $qual, Model $user, string $begruendung): Qual
    {
        $this->assert($this->policy->alsPersonal($user, $qual), 'Die Budgetprüfung ist nicht offen.');
        $this->begruendungPflicht($begruendung);

        return $this->wechseln($qual, $user, QualStatus::BudgetpruefungAbgelehnt, function (Qual $qual) use ($begruendung): void {
            $qual->personalabteilung_status = false;
            $qual->entscheidung_begruendung = $begruendung;
            $qual->save();
        }, $begruendung, true);
    }

    public function abbrechen(Qual $qual, Model $user, string $begruendung): Qual
    {
        $this->assert($user->can('manage-app-maqual'), 'Nur die Personalabteilung kann den Workflow abbrechen.');
        $this->assert(! $qual->status->istBeendet(), 'Der Workflow ist bereits beendet.');
        $this->begruendungPflicht($begruendung);

        return $this->wechseln($qual, $user, QualStatus::Abgebrochen, function (Qual $qual) use ($begruendung): void {
            $qual->entscheidung_begruendung = $begruendung;
            $qual->save();
        }, $begruendung, true);
    }

    public function anmeldebestatigung(Qual $qual, Model $user, UploadedFile $datei): Qual
    {
        $this->assert($this->policy->anmeldung($user, $qual), 'Eine Anmeldebestätigung ist gerade nicht möglich.');
        $this->pdf($datei);

        return $this->wechseln($qual, $user, QualStatus::AnmeldungBestaetigt, function (Qual $qual) use ($user, $datei): void {
            $this->dateiSpeichern($qual, $user, 'anmeldebestatigung', $datei);
            $qual->save();
        });
    }

    public function nichtTeilgenommen(Qual $qual, Model $user, string $begruendung): Qual
    {
        $this->assert($this->policy->nichtTeilgenommen($user, $qual), 'Eine Abmeldung ist gerade nicht möglich.');
        $this->begruendungPflicht($begruendung);

        $qual = $this->wechseln($qual, $user, QualStatus::NichtTeilgenommen, function (Qual $qual) use ($begruendung): void {
            $qual->entscheidung_begruendung = $begruendung;
            $qual->save();
        }, $begruendung);

        $this->benachrichtigungen->nichtTeilgenommen($qual->fresh(['teilnehmer']));

        return $qual;
    }

    public function datumSetzen(Qual $qual, Model $user, ?string $datum): Qual
    {
        $this->assert($this->policy->datumSetzen($user, $qual), 'Das Beginndatum darf nicht geändert werden.');

        $vorher = $qual->datum_beginn?->toDateString();
        $qual->datum_beginn = $datum;
        $qual->save();

        $this->logger->felder(
            $qual,
            $user,
            ['datum_beginn' => $vorher],
            ['datum_beginn' => $datum],
            AktivitaetTyp::FieldChange,
        );

        return $qual->fresh();
    }

    public function teilnahmeBestaetigen(Qual $qual, Model $user, UploadedFile $zertifikat, string $kostenTatsaechlich): Qual
    {
        $this->assert($this->policy->teilnahme($user, $qual), 'Teilnahmebestätigung ist noch nicht fällig oder nicht erlaubt.');
        $this->pdf($zertifikat);

        if (! is_numeric($kostenTatsaechlich) || (float) $kostenTatsaechlich < 0) {
            throw new WorkflowException('Bitte die tatsächlichen Kosten angeben, ohne Reise- und Hotelkosten.');
        }

        return $this->wechseln($qual, $user, QualStatus::TeilnahmeBestaetigt, function (Qual $qual) use ($user, $zertifikat, $kostenTatsaechlich): void {
            $this->dateiSpeichern($qual, $user, 'zertifikat', $zertifikat);
            $qual->kosten_tatsaechlich = $kostenTatsaechlich;
            $qual->save();
            $this->logger->felder(
                $qual,
                $user,
                ['kosten_tatsaechlich' => null],
                ['kosten_tatsaechlich' => number_format((float) $kostenTatsaechlich, 2, '.', '')],
                AktivitaetTyp::CostChange,
                'Tatsächliche Kosten ohne Reise- und Hotelkosten',
            );
        }, benachrichtigeTeilnehmer: 'Bitte bewerten Sie die Qualifizierung.');
    }

    /**
     * @param  array<string, mixed>  $antworten
     */
    public function bewertungSpeichern(Qual $qual, Model $user, array $antworten): Qual
    {
        $this->assert($this->policy->bewerten($user, $qual), 'Die Bewertung ist nicht offen.');

        $daten = Fragebogen::normalisieren($antworten, 'bewertung');

        return $this->wechseln($qual, $user, QualStatus::BewertungErfolgt, function (Qual $qual) use ($daten): void {
            $qual->bewertung()->updateOrCreate(
                ['qual_id' => $qual->getKey()],
                ['data' => $daten],
            );
            $qual->save();
            $this->evaluierung->for($qual)->starten($qual);
        }, benachrichtigeTeilnehmer: 'Bitte führen Sie die Evaluierung gemeinsam mit Ihrer Führungskraft durch.');
    }

    /**
     * @param  array<string, mixed>  $antworten
     */
    public function evaluierungBestaetigen(Qual $qual, Model $user, array $antworten): Qual
    {
        $this->assert($this->policy->evaluieren($user, $qual), 'Die Evaluierung ist nicht offen.');
        if ($qual->evaluierung_provider !== 'in_app') {
            $this->evaluierung->for($qual);
        }

        $daten = Fragebogen::normalisieren($antworten, 'evaluierung');
        $istTeilnehmer = (int) $user->getKey() === (int) $qual->teilnehmer_id;
        $partner = $qual->teilnehmer?->vorgesetzter();
        $istPartner = $partner && (int) $partner->getKey() === (int) $user->getKey();

        if (! $istTeilnehmer && ! $istPartner) {
            throw new WorkflowException('Nur Teilnehmer und Vorgesetzter können die Evaluierung bestätigen.');
        }

        return DB::transaction(function () use ($qual, $user, $daten, $istTeilnehmer, $istPartner, $partner): Qual {
            /** @var Evaluierung $evaluierung */
            $evaluierung = $qual->evaluierung()->firstOrCreate(['qual_id' => $qual->getKey()]);
            $evaluierung->data = $daten;

            if ($istTeilnehmer || ($partner && (int) $partner->getKey() === (int) $qual->teilnehmer_id)) {
                $evaluierung->teilnehmer_bestaetigt_am = now();
            }

            if ($istPartner || $partner === null || ($partner && (int) $partner->getKey() === (int) $qual->teilnehmer_id)) {
                $evaluierung->vorgesetzter_bestaetigt_am = now();
            }

            $evaluierung->save();

            if (! $evaluierung->istVollstaendig()) {
                $this->logger->felder($qual, $user, [], ['evaluierung' => 'bestaetigt'], AktivitaetTyp::FieldChange, 'Evaluierung zwischengespeichert');
                if ($partner && ! $istPartner) {
                    $this->benachrichtigungen->partner($qual, $partner, 'Bitte bestätigen Sie die Evaluierung zu „'.$qual->name.'“.');
                }
                if (! $istTeilnehmer && $qual->teilnehmer) {
                    $this->benachrichtigungen->teilnehmer($qual, 'Bitte bestätigen Sie die Evaluierung zu „'.$qual->name.'“.');
                }

                return $qual->fresh(['evaluierung', 'teilnehmer']);
            }

            $von = $qual->status;
            $qual->status = QualStatus::Abgeschlossen;
            $qual->save();
            $this->logger->status($qual, $user, $von, QualStatus::Abgeschlossen);
            $frisch = $qual->fresh(['teilnehmer', 'anleger', 'evaluierung']);
            $this->benachrichtigungen->entscheidung($frisch, '„'.$qual->name.'“ ist abgeschlossen.');

            return $frisch;
        });
    }

    /**
     * @param  array<string, mixed>  $attribute
     */
    public function korrigieren(Qual $qual, Model $user, array $attribute): Qual
    {
        $this->assert($this->policy->korrigieren($user, $qual), 'Nur die Personalabteilung darf Daten korrigieren.');

        if ((bool) ($attribute['kostenlos'] ?? $qual->kostenlos)) {
            $attribute['kostenlos'] = true;
            $attribute['kosten_voraussichtlich'] = '0.00';
        }

        $aenderungen = [];
        foreach (self::KORREKTUR_FELDER as $feld) {
            if (! array_key_exists($feld, $attribute)) {
                continue;
            }

            $neu = $attribute[$feld];
            $alt = $qual->getAttribute($feld);
            $altWert = $alt instanceof \BackedEnum ? $alt->value : ($alt instanceof \DateTimeInterface ? $alt->format('Y-m-d') : $alt);
            $neuWert = $neu instanceof \BackedEnum ? $neu->value : $neu;

            if ((string) $altWert !== (string) $neuWert) {
                $aenderungen[$feld] = ['alt' => $altWert, 'neu' => $neuWert];
                $qual->setAttribute($feld, $neu === '' ? null : $neu);
            }
        }

        if ($aenderungen === []) {
            return $qual;
        }

        $qual->save();

        $kosten = array_intersect_key($aenderungen, array_flip(['kosten_voraussichtlich', 'kosten_tatsaechlich', 'kostenlos']));
        $andere = array_diff_key($aenderungen, $kosten);

        if ($kosten !== []) {
            $this->logger->felder(
                $qual,
                $user,
                collect($kosten)->map(fn (array $paar) => $paar['alt'])->all(),
                collect($kosten)->map(fn (array $paar) => $paar['neu'])->all(),
                AktivitaetTyp::CostChange,
                'Korrektur durch die Personalabteilung',
            );
        }

        if ($andere !== []) {
            $this->logger->felder(
                $qual,
                $user,
                collect($andere)->map(fn (array $paar) => $paar['alt'])->all(),
                collect($andere)->map(fn (array $paar) => $paar['neu'])->all(),
                AktivitaetTyp::FieldChange,
                'Korrektur durch die Personalabteilung',
            );
        }

        return $qual->fresh();
    }

    public function dateiErsetzen(Qual $qual, Model $user, string $collection, UploadedFile $datei): Qual
    {
        $this->assert($this->policy->korrigieren($user, $qual), 'Nur die Personalabteilung darf Dateien austauschen.');
        $this->assert(in_array($collection, ['anmeldebestatigung', 'zertifikat'], true), 'Unbekannte Datei.');
        $this->pdf($datei);
        $this->dateiSpeichern($qual, $user, $collection, $datei);

        return $qual->fresh();
    }

    public function budgetSetzen(Model $user, int $gvpId, int $kostenjahr, string $betrag): Budget
    {
        $this->assert($user->can('manage-app-maqual'), 'Nur die Personalabteilung darf Budgets festlegen.');

        $gvp = MaqualModels::gvpQuery()->find($gvpId);
        if (! $gvp || ! in_array($gvp->kuerzel, ['GB', 'HGF'], true)) {
            throw new WorkflowException('Budgets sind nur für Geschäftsbereiche möglich.');
        }

        if (! is_numeric($betrag) || (float) $betrag < 0) {
            throw new WorkflowException('Bitte einen gültigen Budgetbetrag angeben.');
        }

        $bestehend = Budget::query()->where('gvp_id', $gvpId)->where('kostenjahr', $kostenjahr)->first();
        $budget = Budget::query()->updateOrCreate(
            ['gvp_id' => $gvpId, 'kostenjahr' => $kostenjahr],
            ['betrag' => $betrag],
        );

        $this->logger->budget(
            $user,
            $bestehend ? ['gvp_id' => $gvpId, 'kostenjahr' => $kostenjahr, 'betrag' => (string) $bestehend->betrag] : null,
            ['gvp_id' => $gvpId, 'kostenjahr' => $kostenjahr, 'betrag' => number_format((float) $betrag, 2, '.', '')],
        );

        return $budget;
    }

    /**
     * @param  array{gl: ?bool, al: ?bool, gf: ?bool}  $status
     */
    private function anfangsStatus(array $status, FreigabeKette $kette): QualStatus
    {
        if ($kette->glId === null && $kette->alId === null && $kette->gfId === null) {
            throw new WorkflowException('Für diesen Mitarbeiter ist keine Freigabekette im GVP hinterlegt.');
        }

        if ($kette->alId !== null && $status['al'] !== true) {
            return QualStatus::Neu;
        }

        if ($kette->gfId !== null && $status['gf'] !== true) {
            return QualStatus::WarteAufGf;
        }

        if ($kette->gfId === null) {
            throw new WorkflowException('Für diesen Mitarbeiter ist kein Geschäftsführer im GVP hinterlegt.');
        }

        return QualStatus::Budgetpruefung;
    }

    /**
     * Stufen, zu denen beim Anlegen ausdrücklich als Stellvertretung gefragt werden kann.
     *
     * @return list<'gl'|'al'|'gf'>
     */
    public function moeglicheStellvertretungen(Model $anleger, Model $teilnehmer): array
    {
        $kette = $this->ketteResolver->fuerTeilnehmer($teilnehmer);

        return $this->ketteResolver->offeneStellvertretungen($anleger, $kette, $this->eigeneStufeFuerAnlage($anleger, $teilnehmer, $kette));
    }

    /**
     * @param  list<string>  $stellvertretend
     * @return array<string, array{status: ?bool, von: ?int, stellvertretung: bool}>
     */
    private function entscheidungenFuerAnlage(Model $anleger, Model $teilnehmer, FreigabeKette $kette, array $stellvertretend): array
    {
        $eigeneStufe = $this->eigeneStufeFuerAnlage($anleger, $teilnehmer, $kette);
        $rang = ['gl' => 1, 'al' => 2, 'gf' => 3];
        $eigeneRang = $eigeneStufe !== null ? $rang[$eigeneStufe] : 0;
        $erlaubt = $this->ketteResolver->offeneStellvertretungen($anleger, $kette, $eigeneStufe);
        $gewuenscht = array_values(array_unique(array_map(strval(...), $stellvertretend)));

        if (array_diff($gewuenscht, $erlaubt) !== []) {
            throw new WorkflowException('Diese Stellvertretung dürfen Sie für diesen Mitarbeiter nicht erteilen.');
        }

        $ids = ['gl' => $kette->glId, 'al' => $kette->alId, 'gf' => $kette->gfId];
        $flags = [];

        foreach ($ids as $stufe => $id) {
            if ($id === null) {
                $flags[$stufe] = ['status' => null, 'von' => null, 'stellvertretung' => false];

                continue;
            }

            if ($rang[$stufe] <= $eigeneRang) {
                $flags[$stufe] = ['status' => true, 'von' => (int) $anleger->getKey(), 'stellvertretung' => false];
            } elseif (in_array($stufe, $gewuenscht, true)) {
                $flags[$stufe] = ['status' => true, 'von' => (int) $anleger->getKey(), 'stellvertretung' => true];
            } else {
                $flags[$stufe] = ['status' => null, 'von' => null, 'stellvertretung' => false];
            }
        }

        $offen = false;
        foreach (['gl', 'al', 'gf'] as $stufe) {
            if ($ids[$stufe] === null) {
                continue;
            }

            if ($flags[$stufe]['status'] !== true) {
                $offen = true;

                continue;
            }

            if ($offen && $flags[$stufe]['stellvertretung']) {
                throw new WorkflowException('Eine höhere Stellvertretung ist erst möglich, wenn die vorherige Stufe zugestimmt hat.');
            }
        }

        return $flags;
    }

    private function eigeneStufeFuerAnlage(Model $anleger, Model $teilnehmer, FreigabeKette $kette): ?string
    {
        if ($anleger->can('manage-app-maqual') && ! $this->policyFuehrtAlsVorgesetzter($anleger, $teilnehmer)) {
            return null;
        }

        return $this->ketteResolver->eigeneStufe($anleger, $kette);
    }

    private function entscheidungFesthalten(Qual $qual, Model $user, string $stufe, bool $zugestimmt): void
    {
        $erwarteterId = $qual->getAttribute($stufe.'_id');
        $qual->setAttribute($stufe.'_status', $zugestimmt);
        $qual->setAttribute($stufe.'_entschieden_von_id', $user->getKey());
        $qual->setAttribute($stufe.'_stellvertretung', $erwarteterId === null || (int) $user->getKey() !== (int) $erwarteterId);
    }

    /**
     * @param  array<string, array{status: ?bool, von: ?int, stellvertretung: bool}>  $flags
     */
    private function anlageBemerkung(array $flags): string
    {
        $labels = ['gl' => 'GL', 'al' => 'AL', 'gf' => 'GF'];
        $stufen = [];

        foreach ($flags as $stufe => $flag) {
            if ($flag['stellvertretung'] && $flag['status'] === true) {
                $stufen[] = $labels[$stufe] ?? $stufe;
            }
        }

        if ($stufen === []) {
            return 'Qualifizierung angelegt';
        }

        return 'Qualifizierung angelegt, Zustimmung als Stellvertretung '.implode(', ', $stufen);
    }

    private function policyFuehrtAlsVorgesetzter(Model $anleger, Model $teilnehmer): bool
    {
        if (! ($anleger->ist_vorgesetzter ?? false)) {
            return false;
        }

        $untergebene = $anleger->getUntergebene(true);
        if ($untergebene === false) {
            return false;
        }

        return $untergebene->contains(
            fn (Model $mitarbeiter): bool => (int) $mitarbeiter->getKey() === (int) $teilnehmer->getKey(),
        );
    }

    /**
     * @param  callable(Qual): void  $mutator
     */
    private function wechseln(
        Qual $qual,
        Model $user,
        QualStatus $nach,
        callable $mutator,
        ?string $bemerkung = null,
        bool $entscheidung = false,
        ?string $benachrichtigeTeilnehmer = null,
    ): Qual {
        $von = $qual->status;

        $qual = DB::transaction(function () use ($qual, $user, $von, $nach, $mutator, $bemerkung): Qual {
            $qual->status = $nach;
            $mutator($qual);
            $this->logger->status($qual, $user, $von, $nach, $bemerkung);

            return $qual->fresh(['teilnehmer', 'anleger']);
        });

        if ($entscheidung) {
            $this->benachrichtigungen->entscheidung(
                $qual,
                '„'.$qual->name.'“ hat den Status „'.$nach->label().'“.'.($bemerkung ? ' Begründung: '.$bemerkung : ''),
            );
        }

        if ($benachrichtigeTeilnehmer) {
            $this->benachrichtigungen->teilnehmer($qual, $benachrichtigeTeilnehmer);
        }

        if ($nach === QualStatus::BewertungErfolgt) {
            $partner = $qual->teilnehmer?->vorgesetzter();
            if ($partner) {
                $this->benachrichtigungen->partner($qual, $partner, 'Bitte führen Sie die Evaluierung zu „'.$qual->name.'“ gemeinsam durch.');
            }
        }

        return $qual;
    }

    private function kostenAendern(Qual $qual, Model $user, string $feld, string $neu, ?string $grund): void
    {
        $alt = number_format((float) $qual->getAttribute($feld), 2, '.', '');
        $formatiert = number_format((float) $neu, 2, '.', '');

        if ($alt === $formatiert) {
            return;
        }

        if (blank($grund)) {
            throw new WorkflowException('Bitte eine Begründung für die Kostenänderung angeben.');
        }

        $qual->setAttribute($feld, $formatiert);
        if ($feld === 'kosten_voraussichtlich' && (float) $formatiert > 0) {
            $qual->kostenlos = false;
        }

        $this->logger->felder(
            $qual,
            $user,
            [$feld => $alt],
            [$feld => $formatiert],
            AktivitaetTyp::CostChange,
            $grund,
        );
    }

    private function dateiSpeichern(Qual $qual, Model $user, string $collection, UploadedFile $datei): void
    {
        $alt = $qual->getFirstMedia($collection)?->file_name;
        $qual->addMedia($datei->getRealPath())
            ->usingFileName($datei->getClientOriginalName())
            ->toMediaCollection($collection);

        $this->logger->datei($qual, $user, $collection, $alt, $datei->getClientOriginalName());
    }

    private function pdf(UploadedFile $datei): void
    {
        if ($datei->getMimeType() !== 'application/pdf' && $datei->getClientOriginalExtension() !== 'pdf') {
            throw new WorkflowException('Es sind nur PDF-Dateien erlaubt.');
        }
    }

    private function begruendungPflicht(string $begruendung): void
    {
        if (mb_strlen(trim($begruendung)) < 3) {
            throw new WorkflowException('Bitte eine Begründung angeben.');
        }
    }

    private function assert(bool $bedingung, string $meldung): void
    {
        if (! $bedingung) {
            throw new WorkflowException($meldung);
        }
    }
}
