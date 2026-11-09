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
    | The table name used to store drafts.
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
    | Publish each new draft immediately after saveDraft() creates it
    | (the draft payload is applied to the model and the draft is marked
    | as published). This can be overridden per model by implementing
    | shouldAutoPublishDraft().
    |
    */
    'auto_publish' => false,

    /*
    |--------------------------------------------------------------------------
    | Max Versions
    |--------------------------------------------------------------------------
    |
    | Maximum number of draft versions to keep per model. Each time a draft
    | is saved, the oldest versions beyond this limit are deleted.
    | Set to 0 for unlimited versions.
    |
    */
    'max_versions' => 10,

    /*
    |--------------------------------------------------------------------------
    | Events
    |--------------------------------------------------------------------------
    |
    | Enable or disable the dispatch of specific events for performance
    | optimization.
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
    | Settings used by the "draftable:clear-old" command.
    |
    | enabled:        schedule "draftable:clear-old --force" daily (requires
    |                 the Laravel scheduler to be running).
    | days_to_keep:   default value of the --days option.
    | keep_published: never delete drafts that have been published.
    |
    */
    'cleanup' => [
        'enabled' => false,
        'days_to_keep' => 90,
        'keep_published' => true,
    ],
];
