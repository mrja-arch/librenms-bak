<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('device_discovery_scans', function (Blueprint $table): void {
            $table->id();
            $table->string('status', 24)->default('queued')->index();
            $table->json('networks');
            $table->unsignedInteger('total_hosts')->default(0);
            $table->unsignedInteger('processed_hosts')->default(0);
            $table->unsignedInteger('candidates_found')->default(0);
            $table->unsignedInteger('requested_by')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->text('error')->nullable();
            $table->timestamps();
        });

        Schema::create('device_discovery_candidates', function (Blueprint $table): void {
            $table->id();
            $table->string('identity_key', 191)->unique();
            $table->string('ip', 45)->nullable()->index();
            $table->string('hostname')->nullable();
            $table->string('sys_name')->nullable();
            $table->text('sys_descr')->nullable();
            $table->string('sys_object_id')->nullable();
            $table->string('os', 64)->nullable();
            $table->string('device_type', 64)->nullable();
            $table->boolean('ping_status')->nullable();
            $table->boolean('snmp_status')->nullable();
            $table->json('source_methods');
            $table->unsignedInteger('source_device_id')->nullable()->index();
            $table->unsignedInteger('source_port_id')->nullable()->index();
            $table->string('status', 24)->default('pending')->index();
            $table->json('metadata')->nullable();
            $table->timestamp('first_seen_at');
            $table->timestamp('last_seen_at');
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('ignored_at')->nullable();
            $table->unsignedInteger('approved_by')->nullable();
            $table->unsignedInteger('ignored_by')->nullable();
            $table->unsignedInteger('managed_device_id')->nullable()->index();
            $table->text('last_error')->nullable();
            $table->timestamps();
        });

        Schema::create('operation_tasks', function (Blueprint $table): void {
            $table->id();
            $table->string('type', 24)->index();
            $table->string('status', 24)->default('queued')->index();
            $table->unsignedInteger('device_id')->nullable()->index();
            $table->unsignedBigInteger('candidate_id')->nullable()->index();
            $table->unsignedBigInteger('scan_id')->nullable()->index();
            $table->unsignedInteger('requested_by')->nullable();
            $table->json('input')->nullable();
            $table->text('output')->nullable();
            $table->text('error')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('operation_tasks');
        Schema::dropIfExists('device_discovery_candidates');
        Schema::dropIfExists('device_discovery_scans');
    }
};
