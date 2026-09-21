<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vouchers', function (Blueprint $table) {
            /*
             * Set the first time a code is rendered onto a sheet. Once stamped,
             * that cleartext must never be printed again — a second run would put
             * two physical cards with the same code into circulation.
             */
            $table->timestamp('printed_at')->nullable()->after('shelf_expires_at');
            $table->index(['batch_id', 'printed_at', 'status']);
        });
    }

    public function down(): void
    {
        Schema::table('vouchers', function (Blueprint $table) {
            $table->dropIndex(['batch_id', 'printed_at', 'status']);
            $table->dropColumn('printed_at');
        });
    }
};
