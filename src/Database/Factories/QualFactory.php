<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppMaqual\Database\Factories;

use App\Models\User;
use Hwkdo\IntranetAppMaqual\Enums\QualQuelle;
use Hwkdo\IntranetAppMaqual\Enums\QualStatus;
use Hwkdo\IntranetAppMaqual\Enums\Qualtyp;
use Hwkdo\IntranetAppMaqual\Models\Qual;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Qual>
 */
class QualFactory extends Factory
{
    protected $model = Qual::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'teilnehmer_id' => User::factory(),
            'angelegt_von_id' => User::factory(),
            'name' => fake()->sentence(3),
            'beschreibung' => fake()->optional()->paragraph(),
            'begruendung' => fake()->sentence(),
            'quelle' => QualQuelle::Manuell,
            'externe_referenz' => null,
            'status' => QualStatus::Neu,
            'qualtyp' => Qualtyp::Fachbezogen,
            'kostenjahr' => (int) now()->year,
            'pflicht' => false,
            'kostenlos' => false,
            'kosten_voraussichtlich' => fake()->randomFloat(2, 100, 2000),
            'kosten_tatsaechlich' => null,
            'datum_beginn' => null,
            'evaluierung_provider' => 'in_app',
        ];
    }
}
