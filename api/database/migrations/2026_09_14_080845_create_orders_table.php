<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table): void {
            $table->id();

            /*
             * Doubles as the Snippe idempotency key, so a retried create call
             * can never take a second payment for the same order. Snippe rejects
             * keys longer than 30 characters, which a 36-character UUID exceeds,
             * so the key sent is this value with the dashes stripped.
             */
            $table->uuid()->unique();

            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('site_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('plan_id')->constrained()->restrictOnDelete();
            $table->foreignId('nas_device_id')->nullable()->constrained()->nullOnDelete();

            // Reserved when the order is created, released if payment fails.
            $table->foreignId('voucher_id')->nullable()->constrained()->nullOnDelete();

            /*
             * pending -> awaiting_payment -> paid -> fulfilled
             *                             \-> failed | expired | voided
             */
            $table->string('status', 20)->default('pending');

            $table->unsignedInteger('amount_minor');
            $table->string('currency', 3)->default('TZS');

            // Normalised to 255XXXXXXXXX before the push is sent.
            $table->string('phone', 20);
            $table->string('customer_name')->nullable();
            $table->string('customer_email')->nullable();

            /*
             * Captured from the captive portal redirect. Used to bind the
             * voucher to the buyer's device and to log them straight in once the
             * USSD authorisation lands.
             */
            $table->char('client_mac', 17)->nullable();
            $table->string('client_ip', 45)->nullable();

            $table->string('snippe_reference')->nullable()->unique();
            $table->string('snippe_external_reference')->nullable();
            $table->string('channel_provider', 32)->nullable();

            // From the webhook's settlement block, for revenue reporting net of
            // mobile money charges.
            $table->unsignedInteger('fees_minor')->nullable();
            $table->unsignedInteger('net_minor')->nullable();

            $table->string('failure_reason')->nullable();

            $table->timestamp('paid_at')->nullable();
            $table->timestamp('fulfilled_at')->nullable();

            // When we stop waiting for authorisation and release the voucher.
            $table->timestamp('expires_at')->nullable();

            $table->timestamps();

            $table->index(['tenant_id', 'status']);
            $table->index(['tenant_id', 'created_at']);
            // Drives the reconciliation sweep over orders still unresolved.
            $table->index(['status', 'expires_at']);
            $table->index('phone');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
