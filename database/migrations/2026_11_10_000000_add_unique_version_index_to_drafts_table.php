<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const string INDEX_NAME = 'drafts_draftable_version_unique';

    /**
     * Run the migrations.
     *
     * Duplicate version numbers created before this constraint existed are
     * renumbered first (per draftable model, keeping their order), so the
     * unique index can be created safely on existing data.
     */
    public function up(): void
    {
        $table = $this->tableName();

        if (! Schema::hasTable($table) || Schema::hasIndex($table, self::INDEX_NAME)) {
            return;
        }

        $this->renumberDuplicateVersions($table);

        Schema::table($table, function (Blueprint $blueprint): void {
            $blueprint->unique(['draftable_type', 'draftable_id', 'version'], self::INDEX_NAME);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $table = $this->tableName();

        if (! Schema::hasTable($table) || ! Schema::hasIndex($table, self::INDEX_NAME)) {
            return;
        }

        Schema::table($table, function (Blueprint $blueprint): void {
            $blueprint->dropUnique(self::INDEX_NAME);
        });
    }

    /**
     * Make the versions of every model affected by duplicates strictly
     * increasing, ordered by version then id. Only the rows that collide
     * (and the ones they would collide with) get a new number.
     */
    private function renumberDuplicateVersions(string $table): void
    {
        $affected = DB::table($table)
            ->select('draftable_type', 'draftable_id')
            ->groupBy('draftable_type', 'draftable_id', 'version')
            ->havingRaw('COUNT(*) > 1')
            ->get()
            ->unique(fn (object $row): string => $row->draftable_type.'|'.$row->draftable_id);

        foreach ($affected as $row) {
            $drafts = DB::table($table)
                ->select('id', 'version')
                ->where('draftable_type', $row->draftable_type)
                ->where('draftable_id', $row->draftable_id)
                ->orderBy('version')
                ->orderBy('id')
                ->get();

            $previous = 0;

            foreach ($drafts as $draft) {
                $version = max((int) $draft->version, $previous + 1);

                if ($version !== (int) $draft->version) {
                    DB::table($table)->where('id', $draft->id)->update(['version' => $version]);
                }

                $previous = $version;
            }
        }
    }

    private function tableName(): string
    {
        return (string) config('laravel-draftable.table_name', 'drafts');
    }
};
