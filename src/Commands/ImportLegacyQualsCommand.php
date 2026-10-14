<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppMaqual\Commands;

use Hwkdo\IntranetAppMaqual\Services\LegacyQualImporter;
use Illuminate\Console\Command;

class ImportLegacyQualsCommand extends Command
{
    protected $signature = 'maqual:import-legacy {--ids=}';

    protected $description = 'Importiert gezielt Qualifizierungen aus der Legacy-MaQual-Datenbank. Ohne konfigurierte Connection passiert nichts.';

    public function handle(LegacyQualImporter $importer): int
    {
        $connection = config('intranet-app-maqual.legacy_connection');
        if (! is_string($connection) || $connection === '') {
            $this->error('legacy_connection ist nicht konfiguriert. Es wurde nichts importiert.');

            return self::FAILURE;
        }

        $idsOption = $this->option('ids');
        if (! is_string($idsOption) || trim($idsOption) === '') {
            $this->error('Bitte die zu importierenden IDs mit --ids=1,2,3 angeben.');

            return self::FAILURE;
        }

        $ids = array_values(array_filter(array_map(
            fn (string $id): int => (int) trim($id),
            explode(',', $idsOption),
        )));

        if ($ids === []) {
            $this->error('Keine gültigen IDs angegeben.');

            return self::FAILURE;
        }

        $importiert = $importer->importFromConnection($connection, $ids);
        $this->info(count($importiert).' Qualifizierungen verarbeitet.');

        return self::SUCCESS;
    }
}
