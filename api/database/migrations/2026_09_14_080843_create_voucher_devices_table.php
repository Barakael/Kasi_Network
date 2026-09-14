<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('voucher_devices', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('voucher_id')->constrained()->cascadeOnDelete();

            /*
             * A smart TV, console or printer cannot complete a captive portal
             * handshake, so its owner binds it from a phone instead. RouterOS
             * then authenticates it silently by MAC address.
             *
             * These MACs are deliberately not written into radcheck. Doing so
             * would put a MAC into the same global username namespace as
             * voucher codes, where two operators binding the same device would
             * collide. FreeRADIUS instead resolves the MAC to its parent
             * voucher through this table, scoped to the tenant that owns the
             * router the request arrived on.
             */
            $table->char('mac', 17);

            $table->string('label')->nullable();

            // Resolved from the OUI prefix so the owner recognises the device.
            $table->string('vendor')->nullable();

            // active | revoked
            $table->string('status', 20)->default('active');

            // portal | console
            $table->string('created_via', 20)->default('portal');

            $table->timestamp('last_seen_at')->nullable();
            $table->timestamps();

            /*
             * One binding per device per operator. Scoped to the tenant rather
             * than global so the same physical device can be used on two
             * different operators' networks.
             */
            $table->unique(['tenant_id', 'mac']);
            $table->index(['voucher_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('voucher_devices');
    }
};
