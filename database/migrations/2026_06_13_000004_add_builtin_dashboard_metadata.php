<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('dashboards', function (Blueprint $table): void {
            $table->string('built_in_key', 64)->nullable()->unique()->after('access');
            $table->unsignedInteger('built_in_version')->nullable()->after('built_in_key');
        });
    }

    public function down(): void
    {
        Schema::table('dashboards', function (Blueprint $table): void {
            $table->dropUnique('dashboards_built_in_key_unique');
            $table->dropColumn(['built_in_key', 'built_in_version']);
        });
    }
};
