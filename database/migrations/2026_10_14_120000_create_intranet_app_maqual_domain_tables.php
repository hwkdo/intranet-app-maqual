<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('intranet_app_maqual_quals', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('teilnehmer_id')->constrained('users', indexName: 'iaq_quals_teilnehmer_fk');
            $table->foreignId('angelegt_von_id')->constrained('users', indexName: 'iaq_quals_anleger_fk');
            $table->string('name');
            $table->text('beschreibung')->nullable();
            $table->text('begruendung');
            $table->string('quelle');
            $table->string('externe_referenz')->nullable();
            $table->string('status');
            $table->string('qualtyp');
            $table->unsignedSmallInteger('kostenjahr');
            $table->boolean('pflicht')->default(false);
            $table->decimal('kosten_voraussichtlich', 10, 2);
            $table->decimal('kosten_tatsaechlich', 10, 2)->nullable();
            $table->date('datum_beginn')->nullable();
            $table->foreignId('gl_id')->nullable()->constrained('users', indexName: 'iaq_quals_gl_fk');
            $table->foreignId('al_id')->nullable()->constrained('users', indexName: 'iaq_quals_al_fk');
            $table->foreignId('gf_id')->nullable()->constrained('users', indexName: 'iaq_quals_gf_fk');
            $table->unsignedTinyInteger('gl_status')->nullable();
            $table->unsignedTinyInteger('al_status')->nullable();
            $table->unsignedTinyInteger('gf_status')->nullable();
            $table->unsignedTinyInteger('personalabteilung_status')->nullable();
            $table->text('entscheidung_begruendung')->nullable();
            $table->string('evaluierung_provider')->default('in_app');
            $table->timestamp('teilnahme_angefordert_am')->nullable();
            $table->unsignedBigInteger('legacy_id')->nullable();
            $table->timestamps();

            $table->unique('legacy_id', 'iaq_quals_legacy_uq');
            $table->index('status', 'iaq_quals_status_idx');
            $table->index('kostenjahr', 'iaq_quals_jahr_idx');
            $table->index(['teilnehmer_id', 'status'], 'iaq_quals_tn_status_idx');
        });

        Schema::create('intranet_app_maqual_budgets', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('gvp_id')->constrained('gvps', indexName: 'iaq_budgets_gvp_fk');
            $table->unsignedSmallInteger('kostenjahr');
            $table->decimal('betrag', 10, 2);
            $table->timestamps();

            $table->unique(['gvp_id', 'kostenjahr'], 'iaq_budgets_gvp_jahr_uq');
        });

        Schema::create('intranet_app_maqual_bewertungen', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('qual_id')->constrained('intranet_app_maqual_quals', indexName: 'iaq_bew_qual_fk')->cascadeOnDelete();
            $table->json('data');
            $table->timestamps();

            $table->unique('qual_id', 'iaq_bew_qual_uq');
        });

        Schema::create('intranet_app_maqual_evaluierungen', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('qual_id')->constrained('intranet_app_maqual_quals', indexName: 'iaq_eval_qual_fk')->cascadeOnDelete();
            $table->json('data')->nullable();
            $table->timestamp('teilnehmer_bestaetigt_am')->nullable();
            $table->timestamp('vorgesetzter_bestaetigt_am')->nullable();
            $table->timestamps();

            $table->unique('qual_id', 'iaq_eval_qual_uq');
        });

        Schema::create('intranet_app_maqual_aktivitaeten', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('qual_id')->nullable()->constrained('intranet_app_maqual_quals', indexName: 'iaq_akt_qual_fk')->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users', indexName: 'iaq_akt_user_fk')->nullOnDelete();
            $table->string('typ');
            $table->json('vorher')->nullable();
            $table->json('nachher')->nullable();
            $table->text('bemerkung')->nullable();
            $table->timestamps();

            $table->index(['qual_id', 'created_at'], 'iaq_akt_qual_created_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('intranet_app_maqual_aktivitaeten');
        Schema::dropIfExists('intranet_app_maqual_evaluierungen');
        Schema::dropIfExists('intranet_app_maqual_bewertungen');
        Schema::dropIfExists('intranet_app_maqual_budgets');
        Schema::dropIfExists('intranet_app_maqual_quals');
    }
};
