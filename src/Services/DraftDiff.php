<?php

declare(strict_types=1);

namespace Grazulex\LaravelDraftable\Services;

use Grazulex\LaravelDraftable\Models\Draft;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

/**
 * Class DraftDiff
 *
 * Service for comparing draft versions and generating diff output.
 * Follows Single Responsibility Principle - only handles version comparison.
 */
class DraftDiff
{
    /**
     * Compare two drafts and return detailed differences
     */
    public static function compare(Draft $draft1, Draft $draft2): array
    {
        $payload1 = $draft1->payload ?? [];
        $payload2 = $draft2->payload ?? [];

        return self::compareArrays($payload1, $payload2);
    }

    /**
     * Compare a draft with the current model state
     *
     * @param  Model&\Grazulex\LaravelDraftable\Traits\HasDrafts  $model
     * @return array<string, array{old: mixed, new: mixed}>
     */
    public static function compareWithModel(Draft $draft, $model): array
    {
        if (! $model instanceof Model || ! in_array(\Grazulex\LaravelDraftable\Traits\HasDrafts::class, class_uses_recursive($model))) {
            throw new InvalidArgumentException('Model must use HasDrafts trait');
        }

        $draftPayload = $draft->payload ?? [];
        $modelPayload = $model->getDraftPayload();

        return self::compareArrays($draftPayload, $modelPayload);
    }

    /**
     * Compare two models
     *
     * @param  Model&\Grazulex\LaravelDraftable\Traits\HasDrafts  $model1
     * @param  Model&\Grazulex\LaravelDraftable\Traits\HasDrafts  $model2
     * @return array<string, array{old: mixed, new: mixed}>
     */
    public static function compareModels($model1, $model2): array
    {
        if (! $model1 instanceof Model || ! in_array(\Grazulex\LaravelDraftable\Traits\HasDrafts::class, class_uses_recursive($model1))) {
            throw new InvalidArgumentException('Model1 must use HasDrafts trait');
        }

        if (! $model2 instanceof Model || ! in_array(\Grazulex\LaravelDraftable\Traits\HasDrafts::class, class_uses_recursive($model2))) {
            throw new InvalidArgumentException('Model2 must use HasDrafts trait');
        }

        $payload1 = $model1->getDraftPayload();
        $payload2 = $model2->getDraftPayload();

        return self::compareArrays($payload1, $payload2);
    }

    /**
     * Get a summary of changes between two drafts
     */
    public static function getSummary(Draft $draft1, Draft $draft2): array
    {
        $diff = self::compare($draft1, $draft2);

        return [
            'total_changes' => count($diff),
            'changed_fields' => array_keys($diff),
            'has_changes' => $diff !== [],
            'version_from' => $draft1->version,
            'version_to' => $draft2->version,
        ];
    }

    /**
     * Format diff output for human reading
     */
    public static function formatForHuman(array $diff): array
    {
        $formatted = [];

        foreach ($diff as $field => $change) {
            $type = $change['type'];
            $old = $change['old'];
            $new = $change['new'];

            switch ($type) {
                case 'added':
                    $formatted[$field] = "Added: {$new}";
                    break;
                case 'removed':
                    $formatted[$field] = "Removed: {$old}";
                    break;
                case 'modified':
                    $formatted[$field] = "Changed from '{$old}' to '{$new}'";
                    break;
            }
        }

        return $formatted;
    }

    /**
     * Compare two arrays and return differences
     */
    protected static function compareArrays(array $array1, array $array2): array
    {
        $differences = [];
        $allKeys = array_unique(array_merge(array_keys($array1), array_keys($array2)));

        foreach ($allKeys as $key) {
            $value1 = $array1[$key] ?? null;
            $value2 = $array2[$key] ?? null;

            if (! self::valuesEqual($value1, $value2)) {
                $differences[$key] = [
                    'old' => $value1,
                    'new' => $value2,
                    'type' => self::getChangeType($value1, $value2),
                ];
            }
        }

        return $differences;
    }

    /**
     * Check if two values are equal (handles arrays and objects)
     */
    protected static function valuesEqual($value1, $value2): bool
    {
        if (is_array($value1) && is_array($value2)) {
            return self::arraysEqual($value1, $value2);
        }

        return $value1 === $value2;
    }

    /**
     * Deep comparison of arrays
     */
    protected static function arraysEqual(array $array1, array $array2): bool
    {
        if (count($array1) !== count($array2)) {
            return false;
        }

        foreach ($array1 as $key => $value) {
            if (! array_key_exists($key, $array2)) {
                return false;
            }

            if (! self::valuesEqual($value, $array2[$key])) {
                return false;
            }
        }

        return true;
    }

    /**
     * Determine the type of change
     */
    protected static function getChangeType($oldValue, $newValue): string
    {
        if ($oldValue === null && $newValue !== null) {
            return 'added';
        }

        if ($oldValue !== null && $newValue === null) {
            return 'removed';
        }

        return 'modified';
    }
}
