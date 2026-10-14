<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppMaqual\Services;

use Hwkdo\IntranetAppMaqual\Exceptions\WorkflowException;
use Illuminate\Validation\ValidationException;

class Fragebogen
{
    /**
     * @return list<array{key: string, label: string, typ: string}>
     */
    public static function bewertung(): array
    {
        return config('intranet-app-maqual.bewertung_fragen', []);
    }

    /**
     * @return list<array{key: string, label: string, typ: string}>
     */
    public static function evaluierung(): array
    {
        return config('intranet-app-maqual.eval_fragen', []);
    }

    /**
     * @return array<int, string>
     */
    public static function noten(): array
    {
        return config('intranet-app-maqual.noten', []);
    }

    /**
     * @return array<string, list<string>>
     */
    public static function regeln(string $prefix, string $art): array
    {
        $fragen = $art === 'evaluierung' ? self::evaluierung() : self::bewertung();
        $regeln = [];

        foreach ($fragen as $frage) {
            $regeln[$prefix.'.'.$frage['key']] = match ($frage['typ']) {
                'note' => ['required', 'integer', 'between:1,6'],
                'bool' => ['required', 'boolean'],
                default => ['nullable', 'string', 'max:5000'],
            };
        }

        return $regeln;
    }

    /**
     * @param  array<string, mixed>  $gespeichert
     * @return array<string, string>
     */
    public static function formular(string $art, array $gespeichert = []): array
    {
        $fragen = $art === 'evaluierung' ? self::evaluierung() : self::bewertung();
        $antworten = [];

        foreach ($fragen as $frage) {
            $wert = $gespeichert[$frage['key']] ?? null;

            if ($wert === null || $wert === '') {
                $antworten[$frage['key']] = '';

                continue;
            }

            if ($frage['typ'] === 'bool') {
                $antworten[$frage['key']] = filter_var($wert, FILTER_VALIDATE_BOOLEAN) ? '1' : '0';

                continue;
            }

            $antworten[$frage['key']] = (string) $wert;
        }

        return $antworten;
    }

    /**
     * @return array<string, string>
     */
    public static function meldungen(string $prefix, string $art): array
    {
        $fragen = $art === 'evaluierung' ? self::evaluierung() : self::bewertung();
        $meldungen = [];

        foreach ($fragen as $frage) {
            if (! in_array($frage['typ'], ['note', 'bool'], true)) {
                continue;
            }

            $meldungen[$prefix.'.'.$frage['key'].'.required'] = 'Bitte wählen.';
        }

        return $meldungen;
    }

    /**
     * @param  array<string, mixed>  $antworten
     * @return array<string, mixed>
     */
    public static function normalisieren(array $antworten, string $art): array
    {
        $fragen = $art === 'evaluierung' ? self::evaluierung() : self::bewertung();
        $normalisiert = [];

        foreach ($fragen as $frage) {
            $wert = $antworten[$frage['key']] ?? null;

            if ($frage['typ'] === 'note') {
                if ($wert === null || $wert === '') {
                    throw new WorkflowException('Bitte alle Schulnoten ausfüllen.');
                }

                $note = (int) $wert;
                if ($note < 1 || $note > 6) {
                    throw new WorkflowException('Schulnoten müssen zwischen 1 und 6 liegen.');
                }

                $normalisiert[$frage['key']] = $note;

                continue;
            }

            if ($frage['typ'] === 'bool') {
                if ($wert === null || $wert === '') {
                    throw new WorkflowException('Bitte alle Ja/Nein-Fragen beantworten.');
                }

                $normalisiert[$frage['key']] = filter_var($wert, FILTER_VALIDATE_BOOLEAN);

                continue;
            }

            $normalisiert[$frage['key']] = $wert === null ? null : (string) $wert;
        }

        return $normalisiert;
    }

    public static function validationException(WorkflowException $exception): ValidationException
    {
        return ValidationException::withMessages([
            'fragebogen' => $exception->getMessage(),
        ]);
    }
}
