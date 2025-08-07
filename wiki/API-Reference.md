# API Reference

Complete API reference for Laravel Draftable components.

## 🎯 HasDrafts Trait

The main trait that adds draft functionality to your Eloquent models.

### Methods

#### Draft Management

```php
// Save current model state as a draft
public function saveDraft(array $additionalData = []): Draft

// Publish the latest unpublished draft
public function publishDraft(): bool

// Check if model has any drafts
public function hasDrafts(): bool

// Get count of drafts for this model
public function getDraftCount(): int

// Get current version number
public function getCurrentVersion(): int
```

#### Version Control

```php
// Restore model to a specific version
public function restoreVersion(int $versionId): bool

// Compare two drafts and return differences
public function compareDrafts(Draft $draft1, Draft $draft2): array
```

### Relationships

```php
// Get all drafts (published and unpublished)
public function drafts(): MorphMany

// Get only unpublished drafts
public function unpublishedDrafts(): Builder

// Get only published drafts  
public function publishedDrafts(): Builder
```

### Configuration Properties

```php
// Enable automatic draft saving on model updates
protected bool $autoSaveDrafts = false;

// Specify which attributes to include in drafts
protected array $draftableAttributes = [];

// Include additional data in draft payload
protected array $additionalDraftData = [];
```

## 🗃️ Draft Model

The Eloquent model representing individual draft versions.

### Properties

```php
// Fillable attributes
protected $fillable = [
    'draftable_type',
    'draftable_id', 
    'payload',
    'version',
    'created_by',
    'published_at'
];

// Casts
protected $casts = [
    'payload' => 'array',
    'version' => 'integer',
    'published_at' => 'datetime'
];
```

### Methods

#### Status Checks

```php
// Check if draft is published
public function isPublished(): bool

// Check if draft is unpublished
public function isUnpublished(): bool

// Mark draft as published
public function markAsPublished(): void
```

#### Payload Operations

```php
// Get value from payload
public function getPayloadValue(string $key, mixed $default = null): mixed

// Set value in payload
public function setPayloadValue(string $key, mixed $value): void
```

#### Model Operations

```php
// Apply draft data to the draftable model
public function applyToModel(): bool

// Get next version number for this model
public function getNextVersion(): int
```

### Relationships

```php
// Get the model this draft belongs to
public function draftable(): MorphTo

// Get the user who created this draft
public function creator(): BelongsTo
```

### Scopes

```php
// Filter published drafts
public function scopePublished(Builder $query): Builder

// Filter unpublished drafts  
public function scopeUnpublished(Builder $query): Builder

// Filter by version number
public function scopeVersion(Builder $query, int $version): Builder
```

## 🔍 DraftDiff Service

Service for comparing draft versions and models.

### Methods

#### Draft Comparison

```php
// Compare two drafts
public function compare(Draft $draft1, Draft $draft2): array

// Compare draft with its draftable model
public function compareWithModel(Model $model, Draft $draft): array

// Compare two models directly
public function compareModels(Model $model1, Model $model2): array
```

#### Analysis

```php
// Get summary of differences
public function getSummary(array $differences): string

// Check if there are any differences
public function hasDifferences(array $differences): bool

// Get count of changed fields
public function getChangeCount(array $differences): int
```

#### Formatting

```php
// Format differences for human reading
public function formatForHumans(array $differences): array

// Format differences as table data
public function formatAsTable(array $differences): array
```

### Return Format

Comparison methods return arrays in this format:

```php
[
    'field_name' => [
        'type' => 'added|removed|modified',
        'old' => 'previous_value',  // null for 'added'
        'new' => 'new_value'        // null for 'removed'
    ]
]
```

## 🎛️ DraftManager Service

Central service for managing draft operations.

### Methods

#### Core Operations

```php
// Save model as draft
public function saveDraft(Model $model, array $additionalData = []): Draft

// Publish latest draft for model
public function publishDraft(Model $model): bool

// Restore model to specific version
public function restoreVersion(Model $model, int $versionId): bool
```

#### Querying

```php
// Get all drafts for a model
public function getDrafts(Model $model): Collection

// Get unpublished drafts for a model
public function getUnpublishedDrafts(Model $model): Collection

// Get published drafts for a model
public function getPublishedDrafts(Model $model): Collection

// Check if model has drafts
public function hasDrafts(Model $model): bool

// Get draft count for model
public function getDraftCount(Model $model): int
```

#### Comparison

```php
// Compare two drafts
public function compareDrafts(Draft $draft1, Draft $draft2): array

// Compare model with draft
public function compareWithDraft(Model $model, Draft $draft): array
```

#### Maintenance

```php
// Clean up old drafts
public function cleanupOldDrafts(int $daysOld = 90): int

// Delete specific draft
public function deleteDraft(Draft $draft): bool
```

## 🎪 Events

Laravel Draftable fires several events during operations.

### DraftCreated

Fired when a new draft is created.

```php
use Grazulex\LaravelDraftable\Events\DraftCreated;

class DraftCreated
{
    public function __construct(
        public Draft $draft
    ) {}
}
```

### DraftPublished

Fired when a draft is published.

```php
use Grazulex\LaravelDraftable\Events\DraftPublished;

class DraftPublished
{
    public function __construct(
        public Draft $draft
    ) {}
}
```

### VersionRestored

Fired when a model is restored to a previous version.

```php
use Grazulex\LaravelDraftable\Events\VersionRestored;

class VersionRestored
{
    public function __construct(
        public Model $model,
        public Draft $draft,
        public int $versionId
    ) {}
}
```

### Event Listeners

```php
// In EventServiceProvider
protected $listen = [
    DraftCreated::class => [
        NotifyEditorsOfNewDraft::class,
        UpdateSearchIndex::class,
    ],
    DraftPublished::class => [
        ClearModelCache::class,
        UpdateSitemap::class,
    ],
    VersionRestored::class => [
        LogVersionRestore::class,
        NotifyModelOwner::class,
    ],
];
```

## ⚙️ Configuration

### Config File

```php
// config/laravel-draftable.php
return [
    // Table name for storing drafts
    'table_name' => 'drafts',
    
    // Automatically publish drafts when created
    'auto_publish' => false,
    
    // Automatically save drafts on model updates
    'auto_save' => false,
    
    // Maximum versions to keep per model (0 = unlimited)
    'max_versions' => 0,
    
    // Days after which to cleanup old drafts (0 = never)
    'cleanup_days' => 0,
];
```

### Service Registration

The package automatically registers these services:

```php
// Available through dependency injection
app(DraftManager::class)
app(DraftDiff::class)

// Or using the container
$this->app->make(DraftManager::class)
$this->app->make(DraftDiff::class)
```

## 📋 Artisan Commands

### laravel-draftable:list

List and filter drafts.

```php
// Command signature
laravel-draftable:list
    {--model= : Filter by model type}
    {--status= : Filter by status (published/unpublished)}
    {--limit=20 : Limit number of results}
```

### laravel-draftable:diff

Compare draft versions.

```php
// Command signature  
laravel-draftable:diff
    {model : Model class name}
    {id : Model ID}
    {--versions= : Comma-separated version numbers}
    {--format=table : Output format (table/json/yaml)}
```

### laravel-draftable:clear-old

Clean up old drafts.

```php
// Command signature
laravel-draftable:clear-old
    {--days=90 : Delete drafts older than X days}
    {--dry-run : Show what would be deleted without deleting}
    {--force : Skip confirmation prompt}
```

## 🔒 Draftable Interface

Contract that draftable models can implement.

```php
interface Draftable
{
    // Get attributes that should be included in drafts
    public function getDraftableAttributes(): array;
    
    // Get additional data to include in draft payload  
    public function getAdditionalDraftData(): array;
    
    // Determine if auto-save is enabled
    public function shouldAutoSaveDrafts(): bool;
}
```

## 🗄️ Database Schema

### Drafts Table

```sql
CREATE TABLE `drafts` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `draftable_type` varchar(255) NOT NULL,
  `draftable_id` bigint unsigned NOT NULL,
  `payload` json NOT NULL,
  `version` bigint unsigned NOT NULL DEFAULT '1',
  `created_by` bigint unsigned DEFAULT NULL,
  `published_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `drafts_draftable_type_draftable_id_index` (`draftable_type`,`draftable_id`),
  KEY `drafts_published_at_index` (`published_at`),
  KEY `drafts_version_index` (`version`),
  KEY `drafts_created_by_foreign` (`created_by`),
  CONSTRAINT `drafts_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`)
);
```

### Performance Indexes

- `draftable_type` + `draftable_id`: Fast model lookups
- `published_at`: Quick published/unpublished filtering  
- `version`: Efficient version queries
- `created_by`: User-based filtering

**🎯 This comprehensive API provides everything needed for enterprise-grade draft management!**
