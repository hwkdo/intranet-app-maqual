<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppMaqual\Enums;

enum QualStatus: string
{
    case Neu = 'neu';
    case WarteAufGf = 'warte_auf_gf';
    case Budgetpruefung = 'budgetpruefung';
    case Abgelehnt = 'abgelehnt';
    case BudgetpruefungAbgelehnt = 'budgetpruefung_abgelehnt';
    case Genehmigt = 'genehmigt';
    case AnmeldungBestaetigt = 'anmeldung_bestaetigt';
    case NichtTeilgenommen = 'nicht_teilgenommen';
    case TeilnahmeBestaetigt = 'teilnahme_bestaetigt';
    case BewertungErfolgt = 'bewertung_erfolgt';
    case Abgeschlossen = 'abgeschlossen';
    case Abgebrochen = 'abgebrochen';

    public function label(): string
    {
        return match ($this) {
            self::Neu => 'Neu',
            self::WarteAufGf => 'Warte auf Geschäftsführung',
            self::Budgetpruefung => 'Budgetprüfung',
            self::Abgelehnt => 'Abgelehnt',
            self::BudgetpruefungAbgelehnt => 'Budgetprüfung abgelehnt',
            self::Genehmigt => 'Genehmigt',
            self::AnmeldungBestaetigt => 'Anmeldung bestätigt',
            self::NichtTeilgenommen => 'Nicht teilgenommen',
            self::TeilnahmeBestaetigt => 'Teilnahme bestätigt',
            self::BewertungErfolgt => 'Bewertung erfolgt',
            self::Abgeschlossen => 'Abgeschlossen',
            self::Abgebrochen => 'Abgebrochen',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Abgelehnt, self::BudgetpruefungAbgelehnt, self::NichtTeilgenommen, self::Abgebrochen => 'red',
            self::Abgeschlossen => 'green',
            self::Genehmigt, self::AnmeldungBestaetigt, self::TeilnahmeBestaetigt, self::BewertungErfolgt => 'lime',
            self::Budgetpruefung, self::WarteAufGf => 'amber',
            self::Neu => 'zinc',
        };
    }

    public function istBeendet(): bool
    {
        return in_array($this, self::beendet(), true);
    }

    /**
     * @return list<self>
     */
    public static function beendet(): array
    {
        return [
            self::Abgeschlossen,
            self::NichtTeilgenommen,
            self::Abgelehnt,
            self::BudgetpruefungAbgelehnt,
            self::Abgebrochen,
        ];
    }

    /**
     * @return list<string>
     */
    public static function beendetValues(): array
    {
        return array_map(fn (self $status): string => $status->value, self::beendet());
    }

    public function zaehltInsBudget(): bool
    {
        return ! in_array($this, [
            self::Abgelehnt,
            self::BudgetpruefungAbgelehnt,
            self::NichtTeilgenommen,
            self::Abgebrochen,
        ], true);
    }
}
