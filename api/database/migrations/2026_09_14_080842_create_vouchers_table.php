<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vouchers', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('plan_id')->constrained()->restrictOnDelete();

            // Null for vouchers sold online rather than printed in a batch.
            $table->foreignId('batch_id')->nullable()
                ->constrained('voucher_batches')->cascadeOnDelete();

            /*
             * The code is encrypted at rest here, so a dump of this table alone
             * yields nothing. Because Laravel's encryption is non-deterministic
             * it cannot be searched, so lookups go through code_hash, a keyed
             * HMAC of the normalised code.
             *
             * The cleartext does still exist in radcheck: MikroTik's hotspot
             * authenticates with CHAP, which requires the RADIUS server to know
             * the password. That table is readable only by the restricted
             * radius account. See docs/deployment-runbook.md.
             */
            $table->text('code');
            $table->char('code_hash', 64)->unique();

            // Lets support staff find a voucher from the last group of
            // characters a caller reads out, without decrypting the table.
            $table->char('code_suffix', 8)->nullable();

            // unused | active | exhausted | expired | disabled
            $table->string('status', 20)->default('unused');

            /*
             * Plan terms are copied onto the voucher when it is issued. Editing
             * a plan afterwards must not silently change what an already-sold
             * voucher entitles its holder to.
             */
            $table->unsignedInteger('validity_seconds');
            $table->unsignedInteger('duration_seconds')->nullable();
            $table->unsignedBigInteger('data_cap_bytes')->nullable();
            $table->unsignedInteger('rate_limit_down_kbps')->nullable();
            $table->unsignedInteger('rate_limit_up_kbps')->nullable();
            $table->unsignedSmallInteger('device_limit')->default(1);
            $table->unsignedInteger('price_minor');

            /*
             * Set from Calling-Station-Id the first time the code authenticates.
             * From then on FreeRADIUS rejects it from any other device, which is
             * what makes a printed code single-use rather than shareable.
             */
            $table->char('bound_mac', 17)->nullable();

            $table->timestamp('first_used_at')->nullable();

            /*
             * The wall clock starts at first login, not at purchase, so an hour
             * bundle bought in the morning is still worth an hour at night.
             * expires_at is null until then.
             */
            $table->timestamp('expires_at')->nullable();

            // Unsold stock write-off date, counted from generation.
            $table->timestamp('shelf_expires_at')->nullable();

            $table->timestamp('disabled_at')->nullable();
            $table->foreignId('disabled_by_user_id')->nullable()
                ->constrained('users')->nullOnDelete();
            $table->string('disable_reason')->nullable();

            $table->timestamps();

            $table->index(['tenant_id', 'status']);
            $table->index(['batch_id', 'status']);
            $table->index('code_suffix');
            $table->index('bound_mac');

            // Drives the sweep that expires activated vouchers past their window.
            $table->index(['status', 'expires_at']);
            // Drives the sweep that writes off unsold printed stock.
            $table->index(['status', 'shelf_expires_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vouchers');
    }
};
