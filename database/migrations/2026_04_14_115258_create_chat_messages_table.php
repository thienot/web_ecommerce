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
        Schema::create('chat_messages', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('customer_id')->nullable(); // ID khách hàng nếu đã login
            $table->string('guest_token')->nullable(); // Token định danh khách vãng lai
            $table->enum('sender', ['customer', 'bot']);
            $table->text('message');
            $table->timestamps();
            
            $table->index(['customer_id', 'guest_token']);
        });
    }
    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('chat_messages');
    }
};
