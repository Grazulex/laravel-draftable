<?php

declare(strict_types=1);

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
        Schema::create('drafts', function (Blueprint $table) {
            $table->id();
            $table->morphs('draftable'); // draftable_type, draftable_id (with auto index)
            $table->json('payload');
            $table->unsignedBigInteger('version')->default(1);
            $table->foreignId('created_by')->nullable()->constrained('users');
            $table->timestamp('published_at')->nullable();
            $table->timestamps();

            // Additional indexes for performance (morphs already creates draftable index)
            $table->index(['version']);
            $table->index(['published_at']);
            $table->index(['created_by']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('drafts');
    }
};
