<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('polls', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('commit_id')->unique()->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('duration');
            $table->timestamp('closes_at')->nullable();
            $table->timestamps();
        });

        Schema::create('poll_options', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('poll_id')->constrained()->cascadeOnDelete();
            $table->string('label', 50);
            $table->unsignedTinyInteger('position');
            $table->timestamps();

            $table->unique(['poll_id', 'position']);
        });

        Schema::create('poll_votes', function (Blueprint $table): void {
            $table->id();
            $table->foreignUuid('poll_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('poll_option_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['poll_id', 'user_id']);
            $table->index('poll_option_id');
            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('poll_votes');
        Schema::dropIfExists('poll_options');
        Schema::dropIfExists('polls');
    }
};
