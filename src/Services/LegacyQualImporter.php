<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppMaqual\Services;

use Hwkdo\IntranetAppMaqual\Enums\QualQuelle;
use Hwkdo\IntranetAppMaqual\Enums\QualStatus;
use Hwkdo\IntranetAppMaqual\Enums\Qualtyp;
use Hwkdo\IntranetAppMaqual\Models\Bewertung;
use Hwkdo\IntranetAppMaqual\Models\Evaluierung;
use Hwkdo\IntranetAppMaqual\Models\Qual;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class LegacyQualImporter
{
    /**
     * @param  array<string, mixed>  $row
     */
    public function importRow(array $row): Qual
    {
        $legacyId = (int) ($row['id'] ?? 0);
        if ($legacyId < 1) {
            throw new RuntimeException('Legacy-Datensatz ohne ID.');
        }

        $vorhanden = Qual::query()->where('legacy_id', $legacyId)->first();
        if ($vorhanden) {
            return $vorhanden;
        }

        $qual = Qual::query()->create($this->map($row));
        $this->importDateien($qual, $row);
        $this->importFormulare($qual, $legacyId);

        return $qual;
    }

    /**
     * @param  list<int>  $ids
     * @return list<Qual>
     */
    public function importFromConnection(string $connection, array $ids): array
    {
        $query = DB::connection($connection)->table('app_maqual_quals')->orderBy('id');
        if ($ids !== []) {
            $query->whereIn('id', $ids);
        }

        $importiert = [];
        foreach ($query->get() as $row) {
            $importiert[] = $this->importRow((array) $row);
        }

        return $importiert;
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    public function map(array $row): array
    {
        $quelle = ((string) ($row['quelle'] ?? 'manuell')) === 'magespraech'
            ? QualQuelle::Magespraech
            : QualQuelle::Manuell;

        $qualtyp = (int) ($row['qualtyp_id'] ?? 1) === 2
            ? Qualtyp::Paedagogisch
            : Qualtyp::Fachbezogen;

        $datum = $row['datum_beginn'] ?? null;
        $kostenjahr = $row['kostenjahr_override'] ?? null;
        if ($kostenjahr === null && $datum) {
            $kostenjahr = (int) substr((string) $datum, 0, 4);
        }
        if ($kostenjahr === null) {
            $created = (string) ($row['created_at'] ?? now()->toDateTimeString());
            $jahr = (int) substr($created, 0, 4);
            $kostenjahr = $quelle === QualQuelle::Magespraech ? $jahr + 1 : $jahr;
        }

        $status = $this->mapStatus((string) ($row['status'] ?? ''), $row);

        return [
            'legacy_id' => (int) $row['id'],
            'teilnehmer_id' => (int) ($row['mitarbeiter_id'] ?? $row['teilnehmer_id']),
            'angelegt_von_id' => (int) ($row['vorgesetzter_id'] ?? $row['mitarbeiter_id'] ?? $row['teilnehmer_id']),
            'name' => (string) ($row['name'] ?? 'Qualifizierung'),
            'beschreibung' => $row['beschreibung'] ?? null,
            'begruendung' => (string) ($row['begruendung'] ?? ''),
            'quelle' => $quelle,
            'externe_referenz' => $quelle === QualQuelle::Magespraech && ! empty($row['magespraech_id'])
                ? (string) $row['magespraech_id']
                : null,
            'status' => $status,
            'qualtyp' => $qualtyp,
            'kostenjahr' => (int) $kostenjahr,
            'pflicht' => (bool) ($row['pflicht'] ?? false),
            'kosten_voraussichtlich' => $row['kosten_vorraussichtlich'] ?? $row['kosten_voraussichtlich'] ?? 0,
            'kosten_tatsaechlich' => $row['kosten_tatsaechlich'] ?? null,
            'datum_beginn' => $datum ?: null,
            'gl_id' => $this->idOderNull($row['gl_id'] ?? null),
            'al_id' => $this->idOderNull($row['al_id'] ?? null),
            'gf_id' => $this->idOderNull($row['gf_id'] ?? null),
            'gl_status' => $this->boolOderNull($row['gl_status'] ?? null),
            'al_status' => $this->boolOderNull($row['al_status'] ?? null),
            'gf_status' => $this->boolOderNull($row['gf_status'] ?? null),
            'personalabteilung_status' => $this->boolOderNull($row['perso_status'] ?? $row['personalabteilung_status'] ?? null),
            'entscheidung_begruendung' => $row['workflow_begruendung'] ?? $row['perso_begruendung'] ?? $row['teilnahme_nicht_teilgenommen_begruendung'] ?? null,
            'evaluierung_provider' => 'in_app',
        ];
    }

    /**
     * @param  array<string, mixed>  $row
     */
    public function mapStatus(string $status, array $row = []): QualStatus
    {
        $direkt = [
            'Neu' => QualStatus::Neu,
            'Warte auf Budgetprüfung' => QualStatus::Budgetpruefung,
            'Genehmigt' => QualStatus::Genehmigt,
            'Abgelehnt' => QualStatus::Abgelehnt,
            'Abgebrochen' => QualStatus::Abgebrochen,
            'Anmeldung bestätigt' => QualStatus::AnmeldungBestaetigt,
            'Keine Teilnahme' => QualStatus::NichtTeilgenommen,
            'Nicht teilgenommen' => QualStatus::NichtTeilgenommen,
            'Teilnahme bestätigt' => QualStatus::TeilnahmeBestaetigt,
            'Bewertung erfolgt' => QualStatus::BewertungErfolgt,
            'Evaluierung erfolgt (abgeschlossen)' => QualStatus::Abgeschlossen,
            'Budgetprüfung' => QualStatus::Budgetpruefung,
            'Budgetprüfung abgelehnt' => QualStatus::BudgetpruefungAbgelehnt,
            'Abgeschlossen' => QualStatus::Abgeschlossen,
        ];

        if (isset($direkt[$status])) {
            return $direkt[$status];
        }

        if (str_starts_with($status, 'Warte auf Bestätigung')) {
            if (($row['al_status'] ?? null) === null && ! empty($row['al_id'])) {
                return QualStatus::Neu;
            }

            return QualStatus::WarteAufGf;
        }

        return QualStatus::tryFrom($status) ?? QualStatus::Neu;
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function importDateien(Qual $qual, array $row): void
    {
        $basis = config('intranet-app-maqual.legacy_files_path');
        if (! is_string($basis) || $basis === '') {
            return;
        }

        $zuordnung = [
            'teilnahmebestaetigung' => 'anmeldebestatigung',
            'zertifikat' => 'zertifikat',
        ];

        foreach ($zuordnung as $spalte => $collection) {
            $dateiname = $row[$spalte] ?? null;
            if (! is_string($dateiname) || $dateiname === '') {
                continue;
            }

            $pfad = rtrim($basis, '/').'/'.$row['id'].'/'.$dateiname;
            if (is_file($pfad)) {
                $qual->addMedia($pfad)->preservingOriginal()->toMediaCollection($collection);
            }
        }
    }

    private function importFormulare(Qual $qual, int $legacyId): void
    {
        $connection = config('intranet-app-maqual.legacy_connection');
        if (! is_string($connection) || $connection === '') {
            return;
        }

        $bewertung = DB::connection($connection)->table('app_maqual_qualbewertung')->where('qual_id', $legacyId)->first();
        if ($bewertung && isset($bewertung->data)) {
            Bewertung::query()->updateOrCreate(
                ['qual_id' => $qual->getKey()],
                ['data' => json_decode((string) $bewertung->data, true) ?? []],
            );
        }

        $eval = DB::connection($connection)->table('app_maqual_qualeval')->where('qual_id', $legacyId)->first();
        if ($eval && isset($eval->data)) {
            Evaluierung::query()->updateOrCreate(
                ['qual_id' => $qual->getKey()],
                ['data' => json_decode((string) $eval->data, true) ?? []],
            );
        }
    }

    private function idOderNull(mixed $wert): ?int
    {
        if (! is_numeric($wert) || (int) $wert < 1) {
            return null;
        }

        return (int) $wert;
    }

    private function boolOderNull(mixed $wert): ?bool
    {
        if ($wert === null || $wert === '') {
            return null;
        }

        return (bool) $wert;
    }
}
