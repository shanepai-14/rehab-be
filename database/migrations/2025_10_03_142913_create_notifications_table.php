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
        Schema::create('notifications', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->string('title', 255);
            $table->text('message');
            $table->string('type', 50)->default('info');
            

            $table->string('related_type')->nullable(); 
            $table->unsignedBigInteger('related_id')->nullable(); 
            
            // Alternative: URL field for direct links
            $table->string('action_url')->nullable(); 
            
            $table->boolean('is_read')->default(false);
            $table->timestamp('read_at')->nullable();
            $table->timestamps();

            // Indexes for better query performance
            $table->index('user_id');
            $table->index('is_read');
            $table->index('created_at');
            $table->index('read_at');
            $table->index(['related_type', 'related_id']); 
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('notifications');
    }
};
