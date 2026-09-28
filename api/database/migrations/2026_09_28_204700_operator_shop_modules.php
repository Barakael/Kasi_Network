<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('agent_site', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('site_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['user_id', 'site_id']);
        });

        Schema::create('customers', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('site_id')->nullable()->constrained()->nullOnDelete();
            $table->string('phone', 16);
            $table->string('last_mac', 17)->nullable();
            $table->timestamp('first_seen_at')->nullable();
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'phone']);
            $table->index(['tenant_id', 'site_id']);
        });

        Schema::create('campaigns', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->text('body');
            $table->string('audience', 20)->default('all');
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['tenant_id', 'is_active']);
        });

        Schema::create('platform_invoices', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('amount_minor');
            $table->string('currency', 3)->default('TZS');
            $table->string('status', 20)->default('issued');
            $table->string('period_label')->nullable();
            $table->date('period_start')->nullable();
            $table->date('period_end')->nullable();
            $table->timestamp('due_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'status']);
        });

        Schema::table('users', function (Blueprint $table): void {
            $table->text('two_factor_secret')->nullable()->after('password');
            $table->timestamp('two_factor_confirmed_at')->nullable()->after('two_factor_secret');
        });

        Schema::table('vouchers', function (Blueprint $table): void {
            $table->foreignId('customer_id')
                ->nullable()
                ->after('batch_id')
                ->constrained()
                ->nullOnDelete();
        });

        Schema::table('plans', function (Blueprint $table): void {
            $table->unsignedInteger('default_price_minor')->nullable()->after('price_minor');
            $table->string('offer_label')->nullable()->after('default_price_minor');
            $table->timestamp('offer_ends_at')->nullable()->after('offer_label');
        });
    }

    public function down(): void
    {
        Schema::table('plans', function (Blueprint $table): void {
            $table->dropColumn(['default_price_minor', 'offer_label', 'offer_ends_at']);
        });

        Schema::table('vouchers', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('customer_id');
        });

        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn(['two_factor_secret', 'two_factor_confirmed_at']);
        });

        Schema::dropIfExists('platform_invoices');
        Schema::dropIfExists('campaigns');
        Schema::dropIfExists('customers');
        Schema::dropIfExists('agent_site');
    }
};
