<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE device_discovery_scans MODIFY networks LONGTEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL');
        DB::statement('ALTER TABLE device_discovery_candidates MODIFY source_methods LONGTEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL');
        DB::statement('ALTER TABLE device_discovery_candidates MODIFY metadata LONGTEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL');
        DB::statement('ALTER TABLE operation_tasks MODIFY input LONGTEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE device_discovery_scans MODIFY networks JSON NOT NULL');
        DB::statement('ALTER TABLE device_discovery_candidates MODIFY source_methods JSON NOT NULL');
        DB::statement('ALTER TABLE device_discovery_candidates MODIFY metadata JSON NULL');
        DB::statement('ALTER TABLE operation_tasks MODIFY input JSON NULL');
    }
};
