<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppMaqual\Support;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

class GbZuordnung
{
    /**
     * @return list<int>
     */
    public function gefuehrteGbIds(Model $user): array
    {
        return MaqualModels::gvpQuery()
            ->whereIn('kuerzel', ['GB', 'HGF'])
            ->where(function ($query) use ($user): void {
                $query->where('vorgesetzter_id', $user->getKey())
                    ->orWhere('stellvertreter_id', $user->getKey());
            })
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->all();
    }

    public function gbIdFuerUser(Model $user): ?int
    {
        $user->loadMissing('gvp.parent.parent.parent');
        $gvp = $user->gvp;
        $guard = 0;

        while ($gvp && $guard < 20) {
            if (in_array($gvp->kuerzel, ['GB', 'HGF'], true)) {
                return (int) $gvp->getKey();
            }

            $gvp = $gvp->parent;
            $guard++;
        }

        return null;
    }

    /**
     * @return list<int>
     */
    public function gvpIdsImBaum(int $gbId): array
    {
        $ids = [$gbId];
        $frontier = [$gbId];
        $guard = 0;

        while ($frontier !== [] && $guard < 20) {
            $children = MaqualModels::gvpQuery()
                ->whereIn('parent_id', $frontier)
                ->pluck('id')
                ->map(fn ($id): int => (int) $id)
                ->all();

            $frontier = array_values(array_diff($children, $ids));
            $ids = array_values(array_unique([...$ids, ...$frontier]));
            $guard++;
        }

        return $ids;
    }

    /**
     * @return list<int>
     */
    public function mitarbeiterIds(int $gbId): array
    {
        return MaqualModels::userQuery()
            ->whereIn('gvp_id', $this->gvpIdsImBaum($gbId))
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->all();
    }

    /**
     * @return Collection<int, Model>
     */
    public function budgetGvps(): Collection
    {
        return MaqualModels::gvpQuery()
            ->whereIn('kuerzel', ['GB', 'HGF'])
            ->orderBy('kuerzel')
            ->orderBy('nummer')
            ->get();
    }
}
