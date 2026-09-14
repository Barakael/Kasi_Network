<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('voucher_batches', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('plan_id')->constrained()->restrictOnDelete();

            // Null means the batch is valid at any of the tenant's sites.
            $table->foreignId('site_id')->nullable()->constrained()->nullOnDelete();

            $table->string('reference');
            $table->unsignedInteger('quantity');

            $table->foreignId('created_by_user_id')->nullable()
                ->constrained('users')->nullOnDelete();

            /*
             * Agents receive whole batches to print and hand out. They can see
             * and print only what is assigned to them.
             */
            $table->foreignId('assigned_agent_id')->nullable()
                ->constrained('users')->nullOnDelete();

            // generating | ready | distributed | disabled
            $table->string('status', 20)->default('generating');

            $table->timestamp('printed_at')->nullable();
            $table->unsignedInteger('print_count')->default(0);
            $table->text('notes')->nullable();

            $table->timestamps();

            $table->unique(['tenant_id', 'reference']);
            $table->index(['tenant_id', 'status']);
            $table->index('assigned_agent_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('voucher_batches');
    }
};
