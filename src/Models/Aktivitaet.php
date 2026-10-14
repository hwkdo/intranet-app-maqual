<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppMaqual\Models;

use Hwkdo\IntranetAppMaqual\Enums\AktivitaetTyp;
use Hwkdo\IntranetAppMaqual\Enums\QualQuelle;
use Hwkdo\IntranetAppMaqual\Enums\QualStatus;
use Hwkdo\IntranetAppMaqual\Enums\Qualtyp;
use Hwkdo\IntranetAppMaqual\Support\MaqualModels;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Aktivitaet extends Model
{
    protected $table = 'intranet_app_maqual_aktivitaeten';

    protected $guarded = [];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'typ' => AktivitaetTyp::class,
            'vorher' => 'array',
            'nachher' => 'array',
        ];
    }

    public function qual(): BelongsTo
    {
        return $this->belongsTo(Qual::class, 'qual_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(MaqualModels::user(), 'user_id');
    }

    public function verlaufSatz(): string
    {
        $name = $this->user?->name ?? 'System';
        $bemerkung = trim((string) $this->bemerkung);

        return match ($this->typ) {
            AktivitaetTyp::StatusChange => $this->statusSatz($name, $bemerkung),
            AktivitaetTyp::CostChange => $this->aenderungsSatz($name, $bemerkung, true),
            AktivitaetTyp::FileReplace => $this->dateiSatz($name),
            AktivitaetTyp::FieldChange => $this->aenderungsSatz($name, $bemerkung, false),
            AktivitaetTyp::BudgetChange => $this->budgetSatz($name),
        };
    }

    private function statusSatz(string $name, string $bemerkung): string
    {
        $vorher = $this->vorher['status'] ?? null;
        $nachher = $this->nachher['status'] ?? null;

        if (! is_string($vorher) || $vorher === $nachher) {
            if ($bemerkung === '' || str_starts_with($bemerkung, 'Qualifizierung angelegt')) {
                if (preg_match('/Stellvertretung (.+)$/u', $bemerkung, $treffer) === 1) {
                    return $name.' hat die Qualifizierung angelegt und als Stellvertretung '.$treffer[1].' zugestimmt.';
                }

                return $name.' hat die Qualifizierung angelegt.';
            }

            return $name.' '.$bemerkung;
        }

        $satz = $name.' '.self::statusAktion($vorher, $nachher).' und den Status von „'.self::statusLabel($vorher).'“ auf „'.self::statusLabel($nachher).'“ gesetzt.';

        if ($bemerkung !== '') {
            $satz .= ' Begründung: '.$bemerkung;
        }

        return $satz;
    }

    private function aenderungsSatz(string $name, string $bemerkung, bool $kosten): string
    {
        $teile = [];

        foreach ($this->nachher ?? [] as $feld => $neu) {
            if (! is_string($feld)) {
                continue;
            }

            $alt = $this->vorher[$feld] ?? null;
            $teile[] = $kosten
                ? $this->kostenTeil((string) $feld, $alt, $neu)
                : self::feldAkkusativ((string) $feld).' von „'.self::anzeige((string) $feld, $alt).'“ zu „'.self::anzeige((string) $feld, $neu).'“ geändert';
        }

        $satz = $name.' hat '.($teile === [] ? 'Daten geändert' : implode(' und ', $teile)).'.';

        if ($bemerkung !== '') {
            $satz .= ' '.$bemerkung;
        }

        return $satz;
    }

    private function kostenTeil(string $feld, mixed $alt, mixed $neu): string
    {
        if (in_array($feld, ['kosten_voraussichtlich', 'kosten_tatsaechlich'], true)) {
            if ($alt === null || $alt === '') {
                return self::feldAkkusativ($feld).' mit '.self::geld($neu).' erfasst';
            }

            return self::feldAkkusativ($feld).' von '.self::geld($alt).' zu '.self::geld($neu).' geändert';
        }

        return self::feldAkkusativ($feld).' von „'.self::anzeige($feld, $alt).'“ zu „'.self::anzeige($feld, $neu).'“ geändert';
    }

    private function dateiSatz(string $name): string
    {
        $label = $this->dateiLabel((string) ($this->nachher['collection'] ?? $this->vorher['collection'] ?? ''));
        $neu = trim((string) ($this->nachher['datei'] ?? ''));
        $alt = trim((string) ($this->vorher['datei'] ?? ''));

        if ($alt === '') {
            return $name.' hat die '.$label.($neu !== '' ? ' „'.$neu.'“' : '').' hochgeladen.';
        }

        return $name.' hat die '.$label.' von „'.$alt.'“ zu „'.$neu.'“ ersetzt.';
    }

    private function budgetSatz(string $name): string
    {
        $jahr = (string) ($this->nachher['kostenjahr'] ?? $this->vorher['kostenjahr'] ?? '');
        $neu = $this->nachher['betrag'] ?? null;
        $alt = $this->vorher['betrag'] ?? null;
        $jahrText = $jahr !== '' ? ' '.$jahr : '';

        if ($alt === null || $alt === '') {
            return $name.' hat das Budget'.$jahrText.' auf '.self::geld($neu).' festgesetzt.';
        }

        return $name.' hat das Budget'.$jahrText.' von '.self::geld($alt).' zu '.self::geld($neu).' geändert.';
    }

    private static function statusAktion(string $vorher, string $nachher): string
    {
        return match (true) {
            $nachher === QualStatus::Abgelehnt->value => 'hat abgelehnt',
            $nachher === QualStatus::BudgetpruefungAbgelehnt->value => 'hat die Budgetprüfung abgelehnt',
            $nachher === QualStatus::Abgebrochen->value => 'hat abgebrochen',
            $nachher === QualStatus::NichtTeilgenommen->value => 'hat festgehalten, dass nicht teilgenommen wurde',
            $nachher === QualStatus::AnmeldungBestaetigt->value => 'hat die Anmeldung bestätigt',
            $nachher === QualStatus::TeilnahmeBestaetigt->value => 'hat die Teilnahme bestätigt',
            $nachher === QualStatus::BewertungErfolgt->value => 'hat die Bewertung abgegeben',
            $nachher === QualStatus::Abgeschlossen->value => 'hat die Evaluierung abgeschlossen',
            $nachher === QualStatus::WarteAufGf->value => 'hat zugestimmt',
            $vorher === QualStatus::WarteAufGf->value && $nachher === QualStatus::Budgetpruefung->value => 'hat die Qualifizierung genehmigt',
            $nachher === QualStatus::Genehmigt->value => 'hat die Budgetprüfung genehmigt',
            default => 'hat geändert',
        };
    }

    private static function feldAkkusativ(string $feld): string
    {
        return match ($feld) {
            'kosten_voraussichtlich' => 'die voraussichtlichen Kosten',
            'kosten_tatsaechlich' => 'die tatsächlichen Kosten',
            'kostenlos' => 'die Kennzeichnung kostenlos',
            'name' => 'den Namen',
            'beschreibung' => 'die Beschreibung',
            'begruendung' => 'die Begründung',
            'qualtyp' => 'den Qualtyp',
            'quelle' => 'die Quelle',
            'kostenjahr' => 'das Kostenjahr',
            'pflicht' => 'die Pflichtkennzeichnung',
            'datum_beginn' => 'den Beginn',
            default => $feld,
        };
    }

    private static function anzeige(string $feld, mixed $wert): string
    {
        if ($wert === null || $wert === '') {
            return '–';
        }

        if (in_array($feld, ['pflicht', 'kostenlos'], true)) {
            return filter_var($wert, FILTER_VALIDATE_BOOLEAN) ? 'ja' : 'nein';
        }

        if ($feld === 'qualtyp') {
            return Qualtyp::tryFrom((string) $wert)?->label() ?? (string) $wert;
        }

        if ($feld === 'quelle') {
            return QualQuelle::tryFrom((string) $wert)?->label() ?? (string) $wert;
        }

        if ($feld === 'datum_beginn' && is_string($wert) && preg_match('/^\d{4}-\d{2}-\d{2}/', $wert) === 1) {
            return substr($wert, 8, 2).'.'.substr($wert, 5, 2).'.'.substr($wert, 0, 4);
        }

        return (string) $wert;
    }

    private static function geld(mixed $wert): string
    {
        if ($wert === null || $wert === '' || ! is_numeric($wert)) {
            return '–';
        }

        return number_format((float) $wert, 2, ',', '.').' €';
    }

    private function dateiLabel(string $collection): string
    {
        return match ($collection) {
            'anmeldebestatigung' => 'Anmeldebestätigung',
            'zertifikat' => 'Zertifikat',
            default => $collection,
        };
    }

    private static function statusLabel(mixed $value): string
    {
        if (! is_string($value)) {
            return '–';
        }

        return QualStatus::tryFrom($value)?->label() ?? $value;
    }
}
