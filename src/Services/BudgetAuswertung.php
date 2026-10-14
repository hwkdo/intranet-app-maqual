<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppMaqual\Services;

use Hwkdo\IntranetAppMaqual\Models\Budget;
use Hwkdo\IntranetAppMaqual\Models\Qual;
use Hwkdo\IntranetAppMaqual\Support\GbZuordnung;
use Illuminate\Support\Collection;

class BudgetAuswertung
{
    public function __construct(
        private readonly GbZuordnung $gb,
    ) {}

    /**
     * @return array{
     *     budget: ?string,
     *     voraussichtlich: string,
     *     tatsaechlich: string,
     *     differenz_voraussichtlich: ?string,
     *     differenz_tatsaechlich: ?string,
     *     quals: Collection<int, Qual>
     * }
     */
    public function fuerGb(int $gvpId, int $kostenjahr): array
    {
        $mitarbeiterIds = $this->gb->mitarbeiterIds($gvpId);

        $quals = Qual::query()
            ->with(['teilnehmer', 'al', 'gf'])
            ->whereIn('teilnehmer_id', $mitarbeiterIds === [] ? [0] : $mitarbeiterIds)
            ->where('kostenjahr', $kostenjahr)
            ->zaehltInsBudget()
            ->orderBy('name')
            ->get();

        $voraussichtlich = $quals->sum(fn (Qual $qual): float => (float) $qual->kosten_voraussichtlich);
        $tatsaechlich = $quals->sum(fn (Qual $qual): float => (float) ($qual->kosten_tatsaechlich ?? 0));

        $budget = Budget::query()
            ->where('gvp_id', $gvpId)
            ->where('kostenjahr', $kostenjahr)
            ->value('betrag');

        $budgetWert = $budget === null ? null : (float) $budget;

        return [
            'budget' => $budgetWert === null ? null : $this->format($budgetWert),
            'voraussichtlich' => $this->format($voraussichtlich),
            'tatsaechlich' => $this->format($tatsaechlich),
            'differenz_voraussichtlich' => $budgetWert === null ? null : $this->format($budgetWert - $voraussichtlich),
            'differenz_tatsaechlich' => $budgetWert === null ? null : $this->format($budgetWert - $tatsaechlich),
            'quals' => $quals,
        ];
    }

    private function format(float $wert): string
    {
        return number_format($wert, 2, '.', '');
    }
}
