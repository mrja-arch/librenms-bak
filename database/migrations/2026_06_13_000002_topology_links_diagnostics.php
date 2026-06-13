<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('links', function (Blueprint $table): void {
            $table->string('status', 16)->default('active')->index();
            $table->unsignedSmallInteger('missed_discoveries')->default(0);
            $table->timestamp('first_seen_at')->nullable()->index();
            $table->timestamp('last_seen_at')->nullable()->index();
            $table->timestamp('stale_at')->nullable()->index();
        });

        Schema::create('link_ignores', function (Blueprint $table): void {
            $table->id();
            $table->unsignedInteger('local_device_id')->index();
            $table->unsignedInteger('local_port_id')->nullable()->index();
            $table->string('protocol', 11);
            $table->string('remote_hostname', 128);
            $table->string('remote_port', 128);
            $table->unsignedInteger('created_by')->nullable();
            $table->text('reason')->nullable();
            $table->timestamps();
            $table->unique(
                ['local_device_id', 'local_port_id', 'protocol', 'remote_hostname', 'remote_port'],
                'link_ignores_fingerprint_unique'
            );
        });

        Schema::create('network_map_positions', function (Blueprint $table): void {
            $table->id();
            $table->unsignedInteger('user_id')->index();
            $table->unsignedInteger('device_id')->index();
            $table->unsignedInteger('device_group_id')->default(0)->index();
            $table->double('x');
            $table->double('y');
            $table->timestamps();
            $table->unique(['user_id', 'device_id', 'device_group_id'], 'network_map_positions_unique');
        });

        Schema::create('diagnostic_bundles', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('operation_task_id')->nullable()->index();
            $table->unsignedInteger('device_id')->nullable()->index();
            $table->unsignedInteger('requested_by')->index();
            $table->string('status', 24)->default('queued')->index();
            $table->string('path')->nullable();
            $table->string('filename')->nullable();
            $table->unsignedBigInteger('size')->nullable();
            $table->char('sha256', 64)->nullable();
            $table->text('error')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('expires_at')->nullable()->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('diagnostic_bundles');
        Schema::dropIfExists('network_map_positions');
        Schema::dropIfExists('link_ignores');

        Schema::table('links', function (Blueprint $table): void {
            $table->dropColumn([
                'status',
                'missed_discoveries',
                'first_seen_at',
                'last_seen_at',
                'stale_at',
            ]);
        });
    }
};
