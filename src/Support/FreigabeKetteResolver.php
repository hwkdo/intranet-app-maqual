<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppMaqual\Support;

use Illuminate\Database\Eloquent\Model;

class FreigabeKetteResolver
{
    public function fuerTeilnehmer(Model $teilnehmer): FreigabeKette
    {
        $teilnehmer->loadMissing('gvp.parent.parent.parent');

        $glId = null;
        $alId = null;
        $gfId = null;
        $gvp = $teilnehmer->gvp;
        $guard = 0;

        while ($gvp && $guard < 20) {
            $kuerzel = (string) $gvp->kuerzel;

            if ($glId === null && in_array($kuerzel, ['G', 'FB'], true)) {
                $glId = $gvp->vorgesetzter_id ? (int) $gvp->vorgesetzter_id : null;
            }

            if ($alId === null && in_array($kuerzel, ['A', 'Stab'], true)) {
                $alId = $gvp->vorgesetzter_id ? (int) $gvp->vorgesetzter_id : null;
            }

            if ($gfId === null && in_array($kuerzel, ['GB', 'HGF'], true)) {
                $gfId = $gvp->vorgesetzter_id ? (int) $gvp->vorgesetzter_id : null;
            }

            $gvp = $gvp->parent;
            $guard++;
        }

        return new FreigabeKette($glId, $alId, $gfId);
    }

    /**
     * Höchste Stufe, auf der der User in dieser Kette als Vorgesetzter eingetragen ist.
     * Stellvertretungen zählen hier nicht.
     */
    public function eigeneStufe(Model $user, FreigabeKette $kette): ?string
    {
        $id = (int) $user->getKey();

        if ($kette->gfId === $id) {
            return 'gf';
        }

        if ($kette->alId === $id) {
            return 'al';
        }

        if ($kette->glId === $id) {
            return 'gl';
        }

        return null;
    }

    /**
     * Stufen dieser Kette, auf denen der User Stellvertreter ist und die über der eigenen Vorgesetztenstufe liegen.
     *
     * @return list<'gl'|'al'|'gf'>
     */
    public function offeneStellvertretungen(Model $user, FreigabeKette $kette, ?string $eigeneStufe): array
    {
        $rang = ['gl' => 1, 'al' => 2, 'gf' => 3];
        $eigeneRang = $eigeneStufe !== null ? $rang[$eigeneStufe] : 0;
        $stufen = [];

        foreach (['gl' => $kette->glId, 'al' => $kette->alId, 'gf' => $kette->gfId] as $stufe => $erwarteterId) {
            if ($erwarteterId === null || $rang[$stufe] <= $eigeneRang) {
                continue;
            }

            if ((int) $user->getKey() === $erwarteterId) {
                continue;
            }

            if ($this->kannStufe($user, $stufe, $erwarteterId)) {
                $stufen[] = $stufe;
            }
        }

        return $stufen;
    }

    /**
     * Höchste Führungsstufe, die der User als Vorgesetzter oder Stellvertreter innehat.
     * Nicht für die implizite Zustimmung beim Anlegen verwenden.
     */
    public function hoechsteStufe(Model $user): ?string
    {
        $gvps = MaqualModels::gvpQuery()
            ->where(function ($query) use ($user): void {
                $query->where('vorgesetzter_id', $user->getKey())
                    ->orWhere('stellvertreter_id', $user->getKey());
            })
            ->get(['kuerzel']);

        $best = null;
        $rank = ['gl' => 1, 'al' => 2, 'gf' => 3];

        foreach ($gvps as $gvp) {
            $stufe = match (true) {
                in_array($gvp->kuerzel, ['GB', 'HGF'], true) => 'gf',
                in_array($gvp->kuerzel, ['A', 'Stab'], true) => 'al',
                in_array($gvp->kuerzel, ['G', 'FB'], true) => 'gl',
                default => null,
            };

            if ($stufe === null) {
                continue;
            }

            if ($best === null || $rank[$stufe] > $rank[$best]) {
                $best = $stufe;
            }
        }

        return $best;
    }

    public function kannStufe(Model $user, string $stufe, ?int $erwarteterVorgesetzterId): bool
    {
        if ($erwarteterVorgesetzterId === null) {
            return false;
        }

        if ((int) $user->getKey() === $erwarteterVorgesetzterId) {
            return true;
        }

        $kuerzel = match ($stufe) {
            'gl' => ['G', 'FB'],
            'al' => ['A', 'Stab'],
            'gf' => ['GB', 'HGF'],
            default => [],
        };

        if ($kuerzel === []) {
            return false;
        }

        return MaqualModels::gvpQuery()
            ->whereIn('kuerzel', $kuerzel)
            ->where('vorgesetzter_id', $erwarteterVorgesetzterId)
            ->where('stellvertreter_id', $user->getKey())
            ->exists();
    }
}
