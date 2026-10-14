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
            $table->boolean('kostenlos')->default(false)->after('pflicht');
        });
    }

    public function down(): void
    {
        Schema::table('intranet_app_maqual_quals', function (Blueprint $table): void {
            $table->dropColumn('kostenlos');
        });
    }
};
