<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('intranet_app_maqual_quals', function (Blueprint $table): void {
            $table->foreignId('gl_entschieden_von_id')->nullable()->after('gf_status')->constrained('users', indexName: 'iaq_quals_gl_von_fk');
            $table->foreignId('al_entschieden_von_id')->nullable()->after('gl_entschieden_von_id')->constrained('users', indexName: 'iaq_quals_al_von_fk');
            $table->foreignId('gf_entschieden_von_id')->nullable()->after('al_entschieden_von_id')->constrained('users', indexName: 'iaq_quals_gf_von_fk');
            $table->boolean('gl_stellvertretung')->default(false)->after('gf_entschieden_von_id');
            $table->boolean('al_stellvertretung')->default(false)->after('gl_stellvertretung');
            $table->boolean('gf_stellvertretung')->default(false)->after('al_stellvertretung');
        });
    }

    public function down(): void
    {
        Schema::table('intranet_app_maqual_quals', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('gl_entschieden_von_id');
            $table->dropConstrainedForeignId('al_entschieden_von_id');
            $table->dropConstrainedForeignId('gf_entschieden_von_id');
            $table->dropColumn(['gl_stellvertretung', 'al_stellvertretung', 'gf_stellvertretung']);
        });
    }
};
