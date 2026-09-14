<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('nas_devices', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('site_id')->constrained()->cascadeOnDelete();

            $table->string('name');

            /*
             * The router's address as FreeRADIUS sees it. Mirrored into the
             * `nas` table that FreeRADIUS reads its client list from, and
             * matched against NAS-IP-Address when resolving which tenant an
             * incoming request belongs to.
             */
            $table->string('nasname', 128)->unique();

            /*
             * FreeRADIUS needs the shared secret in cleartext, so the
             * authoritative plaintext copy lives in the `nas` table which only
             * the restricted radius account can read. This encrypted copy is
             * what the console displays and re-syncs from.
             */
            $table->text('shared_secret');

            // RouterOS API, used to read the hotspot host table when a user is
            // choosing which nearby device to bind a voucher to.
            $table->string('api_host')->nullable();
            $table->unsignedSmallInteger('api_port')->default(8728);
            $table->string('api_username')->nullable();
            $table->text('api_password')->nullable();
            $table->boolean('api_uses_tls')->default(false);

            // RouterOS ignores CoA and Disconnect messages until
            // `/radius incoming set accept=yes` is applied.
            $table->unsignedSmallInteger('coa_port')->default(3799);

            $table->string('model')->nullable();
            $table->string('routeros_version', 32)->nullable();
            $table->string('status', 20)->default('active');
            $table->timestamp('last_seen_at')->nullable();
            $table->string('last_probe_status', 20)->nullable();
            $table->text('last_probe_message')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['tenant_id', 'status']);
            $table->index('site_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('nas_devices');
    }
};
