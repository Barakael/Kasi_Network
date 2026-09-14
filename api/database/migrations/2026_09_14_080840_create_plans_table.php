<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plans', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();

            $table->string('name');
            $table->string('description')->nullable();

            // hourly | daily | weekly | monthly | custom
            $table->string('billing_period', 20);

            /*
             * Two independent limits, because "a one hour bundle" and "ten hours
             * of usage within a month" are both things operators sell:
             *
             *   validity_seconds - wall-clock window that starts on first login
             *                      and expires whether or not the client is on.
             *   duration_seconds - cumulative online time across all sessions.
             *                      Null means only the wall clock applies.
             */
            $table->unsignedInteger('validity_seconds');
            $table->unsignedInteger('duration_seconds')->nullable();

            // Null means uncapped data.
            $table->unsignedBigInteger('data_cap_bytes')->nullable();

            $table->unsignedInteger('price_minor');

            $table->unsignedInteger('rate_limit_down_kbps')->nullable();
            $table->unsignedInteger('rate_limit_up_kbps')->nullable();

            /*
             * Simultaneous-Use. One voucher, one device by default; raising it
             * lets a household share a single code.
             */
            $table->unsignedSmallInteger('device_limit')->default(1);

            /*
             * disconnect - end the session when the quota is gone.
             * throttle   - keep the session up at the reduced rate below,
             *              pushed to the router as a rate-limit CoA.
             */
            $table->string('on_quota_exhausted', 20)->default('disconnect');
            $table->unsignedInteger('throttle_down_kbps')->nullable();
            $table->unsignedInteger('throttle_up_kbps')->nullable();

            /*
             * How long a printed voucher stays redeemable before it is written
             * off, counted from generation rather than first use. Null lets
             * unsold stock live indefinitely.
             */
            $table->unsignedSmallInteger('shelf_life_days')->nullable();

            $table->boolean('is_active')->default(true);
            $table->boolean('is_sold_online')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);

            $table->timestamps();
            $table->softDeletes();

            $table->unique(['tenant_id', 'name']);
            $table->index(['tenant_id', 'is_active', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('plans');
    }
};
