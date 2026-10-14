<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppMaqual\Models;

use Hwkdo\IntranetAppMaqual\Casts\NullableBoolean;
use Hwkdo\IntranetAppMaqual\Database\Factories\QualFactory;
use Hwkdo\IntranetAppMaqual\Enums\QualQuelle;
use Hwkdo\IntranetAppMaqual\Enums\QualStatus;
use Hwkdo\IntranetAppMaqual\Enums\Qualtyp;
use Hwkdo\IntranetAppMaqual\Support\MaqualModels;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class Qual extends Model implements HasMedia
{
    use HasFactory;
    use InteractsWithMedia;

    protected $table = 'intranet_app_maqual_quals';

    protected $guarded = [];

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('anmeldebestatigung')
            ->singleFile()
            ->useDisk('local')
            ->acceptsMimeTypes(['application/pdf']);

        $this->addMediaCollection('zertifikat')
            ->singleFile()
            ->useDisk('local')
            ->acceptsMimeTypes(['application/pdf']);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => QualStatus::class,
            'quelle' => QualQuelle::class,
            'qualtyp' => Qualtyp::class,
            'pflicht' => 'boolean',
            'kostenlos' => 'boolean',
            'kosten_voraussichtlich' => 'decimal:2',
            'kosten_tatsaechlich' => 'decimal:2',
            'datum_beginn' => 'date',
            'gl_status' => NullableBoolean::class,
            'al_status' => NullableBoolean::class,
            'gf_status' => NullableBoolean::class,
            'gl_stellvertretung' => 'boolean',
            'al_stellvertretung' => 'boolean',
            'gf_stellvertretung' => 'boolean',
            'personalabteilung_status' => NullableBoolean::class,
            'teilnahme_angefordert_am' => 'datetime',
            'kostenjahr' => 'integer',
            'legacy_id' => 'integer',
        ];
    }

    protected static function newFactory(): QualFactory
    {
        return QualFactory::new();
    }

    public function teilnehmer(): BelongsTo
    {
        return $this->belongsTo(MaqualModels::user(), 'teilnehmer_id');
    }

    public function anleger(): BelongsTo
    {
        return $this->belongsTo(MaqualModels::user(), 'angelegt_von_id');
    }

    public function gl(): BelongsTo
    {
        return $this->belongsTo(MaqualModels::user(), 'gl_id');
    }

    public function al(): BelongsTo
    {
        return $this->belongsTo(MaqualModels::user(), 'al_id');
    }

    public function gf(): BelongsTo
    {
        return $this->belongsTo(MaqualModels::user(), 'gf_id');
    }

    public function glEntschiedenVon(): BelongsTo
    {
        return $this->belongsTo(MaqualModels::user(), 'gl_entschieden_von_id');
    }

    public function alEntschiedenVon(): BelongsTo
    {
        return $this->belongsTo(MaqualModels::user(), 'al_entschieden_von_id');
    }

    public function gfEntschiedenVon(): BelongsTo
    {
        return $this->belongsTo(MaqualModels::user(), 'gf_entschieden_von_id');
    }

    public function freigabeText(string $stufe): string
    {
        $label = match ($stufe) {
            'gl' => 'GL',
            'al' => 'AL',
            'gf' => 'GF',
            default => $stufe,
        };
        $status = $this->getAttribute($stufe.'_status');
        $rollenName = $this->getRelationValue($stufe)?->name;
        $entschiedenVon = $this->getRelationValue($stufe.'EntschiedenVon')?->name;
        $stellvertretung = (bool) $this->getAttribute($stufe.'_stellvertretung');

        if ($status === null) {
            return $label.' '.($rollenName ?? '–');
        }

        $entscheidung = $status ? 'zugestimmt' : 'abgelehnt';

        if ($stellvertretung && is_string($entschiedenVon) && $entschiedenVon !== '') {
            return $entschiedenVon.' (Stellvertretung '.$label.') '.$entscheidung;
        }

        if (is_string($entschiedenVon) && $entschiedenVon !== '' && (int) $this->getAttribute($stufe.'_entschieden_von_id') !== (int) $this->getAttribute($stufe.'_id')) {
            return $entschiedenVon.' '.$entscheidung;
        }

        return $label.' '.($rollenName ?? $entschiedenVon ?? '–').' '.$entscheidung;
    }

    public function bewertung(): HasOne
    {
        return $this->hasOne(Bewertung::class, 'qual_id');
    }

    public function evaluierung(): HasOne
    {
        return $this->hasOne(Evaluierung::class, 'qual_id');
    }

    public function aktivitaeten(): HasMany
    {
        return $this->hasMany(Aktivitaet::class, 'qual_id')->latest();
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeLaufend(Builder $query): Builder
    {
        return $query->whereNotIn('status', QualStatus::beendetValues());
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeBeendet(Builder $query): Builder
    {
        return $query->whereIn('status', QualStatus::beendetValues());
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeZaehltInsBudget(Builder $query): Builder
    {
        return $query->whereNotIn('status', [
            QualStatus::Abgelehnt->value,
            QualStatus::BudgetpruefungAbgelehnt->value,
            QualStatus::NichtTeilgenommen->value,
            QualStatus::Abgebrochen->value,
        ]);
    }

    public function workflowStand(): string
    {
        return match ($this->status) {
            QualStatus::Neu => $this->warteAuf($this->al?->name, 'AL'),
            QualStatus::WarteAufGf => $this->warteAuf($this->gf?->name, 'GF'),
            QualStatus::Budgetpruefung => 'Warte auf die Personalabteilung',
            QualStatus::Genehmigt => $this->warteAuf($this->teilnehmer?->name, 'Anmeldebestätigung'),
            QualStatus::AnmeldungBestaetigt => $this->warteAuf($this->teilnehmer?->name, 'Teilnahmebestätigung'),
            QualStatus::TeilnahmeBestaetigt => $this->warteAuf($this->teilnehmer?->name, 'Bewertung'),
            QualStatus::BewertungErfolgt => 'Warte auf die Evaluierung',
            default => $this->status->label(),
        };
    }

    private function warteAuf(?string $name, string $rolle): string
    {
        if ($name === null || $name === '') {
            return 'Warte auf '.$rolle;
        }

        return 'Warte auf '.$name.' ('.$rolle.')';
    }

    public function teilnahmeFällig(): bool
    {
        return $this->status === QualStatus::AnmeldungBestaetigt
            && $this->datum_beginn !== null
            && $this->datum_beginn->lte(today());
    }

    public function voraussichtlicheKostenAnzeige(): string
    {
        if ($this->kostenlos) {
            return 'Kostenlos';
        }

        return number_format((float) $this->kosten_voraussichtlich, 2, ',', '.').' € + MwSt.';
    }

    public function tatsaechlicheKostenAnzeige(): string
    {
        if ($this->kosten_tatsaechlich === null) {
            return '–';
        }

        return number_format((float) $this->kosten_tatsaechlich, 2, ',', '.').' €';
    }

    public function kostenDifferenzAnzeige(): ?string
    {
        $differenz = $this->kostenDifferenz();

        if ($differenz === null) {
            return null;
        }

        $formatiert = number_format(abs($differenz), 2, ',', '.').' €';

        if ($differenz > 0) {
            return '+'.$formatiert;
        }

        if ($differenz < 0) {
            return '-'.$formatiert;
        }

        return $formatiert;
    }

    public function kostenDifferenzFarbe(): string
    {
        $differenz = $this->kostenDifferenz();

        if ($differenz === null || $differenz === 0.0) {
            return 'neutral';
        }

        return $differenz > 0 ? 'positiv' : 'negativ';
    }

    private function kostenDifferenz(): ?float
    {
        if ($this->kosten_tatsaechlich === null) {
            return null;
        }

        return round((float) $this->kosten_voraussichtlich - (float) $this->kosten_tatsaechlich, 2);
    }
}
