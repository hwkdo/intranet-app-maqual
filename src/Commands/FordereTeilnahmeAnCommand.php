<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppMaqual\Commands;

use Hwkdo\IntranetAppMaqual\Enums\QualStatus;
use Hwkdo\IntranetAppMaqual\Models\Qual;
use Hwkdo\IntranetAppMaqual\Services\QualBenachrichtigungen;
use Illuminate\Console\Command;

class FordereTeilnahmeAnCommand extends Command
{
    protected $signature = 'maqual:fordere-teilnahme';

    protected $description = 'Fordert Teilnehmende zur Zertifikats- und Kostenangabe auf, sobald die Qualifizierung begonnen hat.';

    public function handle(QualBenachrichtigungen $benachrichtigungen): int
    {
        $quals = Qual::query()
            ->with('teilnehmer')
            ->where('status', QualStatus::AnmeldungBestaetigt)
            ->whereDate('datum_beginn', '<=', today())
            ->whereNull('teilnahme_angefordert_am')
            ->get();

        foreach ($quals as $qual) {
            $benachrichtigungen->teilnehmer(
                $qual,
                'Bitte laden Sie das Zertifikat oder die Teilnahmebestätigung hoch und tragen Sie die tatsächlichen Kosten ein.',
            );
            $qual->forceFill(['teilnahme_angefordert_am' => now()])->save();
        }

        $this->info($quals->count().' Teilnahme-Aufforderungen versendet.');

        return self::SUCCESS;
    }
}
