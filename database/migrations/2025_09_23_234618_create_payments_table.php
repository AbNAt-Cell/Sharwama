<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            // --- essential payment details ---
            $table->string('reference')->unique();        // unique payment reference
            $table->decimal('amount', 12, 2);             // payment amount
            $table->string('currency', 10)->default('NGN');
            $table->string('customer_name');
            $table->string('customer_email');
            $table->string('status')->default('pending'); // pending, success, failed
            $table->string('transaction_id')->nullable(); // Monnify transaction ID
            $table->json('gateway_response')->nullable(); // raw Monnify response (optional)

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
