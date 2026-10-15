<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('commits', function (Blueprint $table): void {
            $table->foreignUuid('quoted_commit_id')->nullable()->index()->constrained('commits')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('commits', function (Blueprint $table): void {
            $table->dropForeign(['quoted_commit_id']);
            $table->dropIndex(['quoted_commit_id']);
            $table->dropColumn('quoted_commit_id');
        });
    }
};
