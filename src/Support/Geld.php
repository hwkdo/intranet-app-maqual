<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppMaqual\Support;

class Geld
{
    public static function eingabe(float|string|null $betrag): string
    {
        if ($betrag === null || $betrag === '') {
            return '';
        }

        return number_format(self::zahl((string) $betrag), 2, ',', '');
    }

    public static function normalisieren(string $betrag): string
    {
        $betrag = trim($betrag);

        if ($betrag === '') {
            return '';
        }

        return number_format(self::zahl($betrag), 2, '.', '');
    }

    private static function zahl(string $betrag): float
    {
        $betrag = str_replace(' ', '', $betrag);

        if (str_contains($betrag, ',') && str_contains($betrag, '.')) {
            $betrag = str_replace('.', '', $betrag);
            $betrag = str_replace(',', '.', $betrag);
        } elseif (str_contains($betrag, ',')) {
            $betrag = str_replace(',', '.', $betrag);
        }

        return (float) $betrag;
    }
}
