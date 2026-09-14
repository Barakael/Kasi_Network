<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('webhook_events', function (Blueprint $table): void {
            $table->id();

            // Resolved from the per-tenant webhook path before verification.
            $table->foreignId('tenant_id')->nullable()->constrained()->nullOnDelete();

            /*
             * Snippe's event id (evt_...). Snippe retries a delivery up to five
             * times and states the same event may arrive more than once, so this
             * unique index is what stops one payment issuing two vouchers.
             */
            $table->string('event_id')->unique();

            $table->string('event_type', 64);
            $table->string('reference')->nullable();

            $table->json('payload');

            /*
             * Recorded rather than assumed. snippe/snippe-php v1 performs no
             * signature verification despite its documentation claiming
             * otherwise, so Kasi verifies the HMAC itself and stores the outcome
             * here for audit.
             */
            $table->boolean('signature_verified')->default(false);

            $table->timestamp('processed_at')->nullable();
            $table->text('processing_error')->nullable();
            $table->timestamps();

            $table->index(['event_type', 'processed_at']);
            $table->index('reference');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('webhook_events');
    }
};
