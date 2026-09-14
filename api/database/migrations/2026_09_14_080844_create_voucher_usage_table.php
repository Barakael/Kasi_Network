<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('voucher_usage', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('voucher_id')->unique()->constrained()->cascadeOnDelete();

            /*
             * Mirrors radcheck.username, which is the voucher code. FreeRADIUS
             * only ever knows the User-Name of the request, so the sqlcounter
             * queries look rows up by this column rather than by voucher_id.
             */
            $table->string('username', 64)->unique();

            /*
             * Closed-session totals. The alternative -- having sqlcounter run
             * SUM() across radacct on every Access-Request -- means a growing
             * table scan on the hot authentication path, which is what makes
             * naive hotspot billing systems collapse under a few hundred users.
             * Sessions still open are added as a small delta at query time.
             */
            $table->unsignedBigInteger('seconds_used')->default(0);
            $table->unsignedBigInteger('bytes_in')->default(0);
            $table->unsignedBigInteger('bytes_out')->default(0);

            $table->unsignedInteger('session_count')->default(0);
            $table->timestamp('last_session_at')->nullable();

            /*
             * Watermark: accounting rows that stopped at or before this instant
             * are already folded into the totals above. The rollup job re-reads
             * a short overlap behind it so an interim update that landed mid
             * sweep is not missed.
             */
            $table->timestamp('rolled_up_through')->nullable();

            $table->timestamps();

            $table->index('tenant_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('voucher_usage');
    }
};
