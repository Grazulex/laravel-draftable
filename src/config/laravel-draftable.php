<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Package Enabled
    |--------------------------------------------------------------------------
    |
    | Determine if the Laravel Draftable package is enabled.
    |
    */
    'enabled' => true,

    /*
    |--------------------------------------------------------------------------
    | Draft Table Name
    |--------------------------------------------------------------------------
    |
    | The table name used to store drafts when using separate table strategy.
    |
    */
    'table_name' => 'drafts',

    /*
    |--------------------------------------------------------------------------
    | User Model
    |--------------------------------------------------------------------------
    |
    | The model used for tracking who created drafts.
    |
    */
    'user_model' => 'App\\Models\\User',

    /*
    |--------------------------------------------------------------------------
    | Auto Save Draft
    |--------------------------------------------------------------------------
    |
    | Automatically save a draft when a model is being saved.
    | This can be overridden per model by implementing shouldAutoSaveDraft().
    |
    */
    'auto_save_draft' => false,

    /*
    |--------------------------------------------------------------------------
    | Auto Publish
    |--------------------------------------------------------------------------
    |
    | Automatically publish the latest draft when certain conditions are met.
    |
    */
    'auto_publish' => false,

    /*
    |--------------------------------------------------------------------------
    | Max Versions
    |--------------------------------------------------------------------------
    |
    | Maximum number of draft versions to keep per model.
    | Set to 0 for unlimited versions.
    |
    */
    'max_versions' => 10,

    /*
    |--------------------------------------------------------------------------
    | Storage Strategy
    |--------------------------------------------------------------------------
    |
    | How to store drafts: 'separate_table' (recommended) or 'same_table'.
    |
    | Supported: "separate_table", "same_table"
    |
    */
    'storage_strategy' => 'separate_table',

    /*
    |--------------------------------------------------------------------------
    | Events
    |--------------------------------------------------------------------------
    |
    | Enable or disable specific events for performance optimization.
    |
    */
    'events' => [
        'draft_created' => true,
        'draft_published' => true,
        'version_restored' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Cleanup Settings
    |--------------------------------------------------------------------------
    |
    | Settings for automatic cleanup of old drafts.
    |
    */
    'cleanup' => [
        'enabled' => false,
        'days_to_keep' => 30,
        'keep_published' => true,
    ],
];
