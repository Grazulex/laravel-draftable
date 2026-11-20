<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Adds the date at which a draft is scheduled to be published.
     */
    public function up(): void
    {
        $table = $this->tableName();

        if (! Schema::hasTable($table) || Schema::hasColumn($table, 'publish_at')) {
            return;
        }

        Schema::table($table, function (Blueprint $blueprint): void {
            $blueprint->timestamp('publish_at')->nullable()->after('published_at');
            $blueprint->index(['publish_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $table = $this->tableName();

        if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'publish_at')) {
            return;
        }

        Schema::table($table, function (Blueprint $blueprint): void {
            $blueprint->dropIndex(['publish_at']);
            $blueprint->dropColumn('publish_at');
        });
    }

    private function tableName(): string
    {
        return (string) config('laravel-draftable.table_name', 'drafts');
    }
};
