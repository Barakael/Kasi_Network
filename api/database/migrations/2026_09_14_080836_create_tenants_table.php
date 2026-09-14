<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenants', function (Blueprint $table): void {
            $table->id();

            /*
             * Public identifier. Snippe webhook URLs are per tenant so the
             * correct signing key can be selected before verifying, and those
             * URLs must not expose a guessable sequential id.
             */
            $table->uuid()->unique();

            $table->string('name');
            $table->string('slug')->unique();

            /*
             * Prepended to every voucher code this tenant issues. Codes double
             * as RADIUS usernames, which share one global namespace, so the
             * prefix is what keeps two operators from colliding.
             */
            $table->string('code_prefix', 6)->unique();

            $table->string('status', 20)->default('active');
            $table->string('currency', 3)->default('TZS');
            $table->string('timezone', 64)->default('Africa/Dar_es_Salaam');

            // Snippe credentials are per tenant; each operator settles into
            // their own mobile money account.
            $table->text('snippe_api_key')->nullable();
            $table->text('snippe_webhook_secret')->nullable();

            // Captive portal branding.
            $table->string('portal_name')->nullable();
            $table->string('logo_path')->nullable();
            $table->string('primary_color', 9)->nullable();
            $table->string('support_phone', 20)->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenants');
    }
};
