# Examples

This page contains practical examples of using **Laravel Draftable** in real-world scenarios, based on our comprehensive test suite.

## 📝 Basic Draft Operations

### Creating and Managing Drafts

```php
<?php

use App\Models\Post;
use Grazulex\LaravelDraftable\Traits\HasDrafts;

class Post extends Model
{
    use HasDrafts;
    
    protected $fillable = ['title', 'content', 'status', 'published_at'];
}

// Create a new post
$post = Post::create([
    'title' => 'My First Blog Post',
    'content' => 'Initial content',
    'status' => 'draft'
]);

// Make changes and save as draft
$post->title = 'Updated Blog Post Title';
$post->content = 'This content has been revised and improved.';
$post->saveDraft();

// Check if model has drafts
if ($post->hasDrafts()) {
    echo "Post has " . $post->getDraftCount() . " draft(s)";
}

// Publish the latest draft
$post->publishDraft();
```

### Working with Draft History

```php
// Get all drafts for a model
$allDrafts = $post->drafts; // All drafts (published and unpublished)
$unpublishedDrafts = $post->unpublishedDrafts; // Only unpublished
$publishedDrafts = $post->publishedDrafts; // Only published

// Get current version number
$currentVersion = $post->getCurrentVersion(); // Returns integer

// Access specific draft data
foreach ($post->drafts as $draft) {
    echo "Version {$draft->version} created at {$draft->created_at}";
    echo "Status: " . ($draft->isPublished() ? 'Published' : 'Draft');
    
    // Access draft payload
    $draftData = $draft->payload;
    echo "Title in this version: " . $draftData['title'];
}
```

## 🔍 Version Comparison

### Comparing Draft Versions

```php
use Grazulex\LaravelDraftable\Services\DraftDiff;

// Inject the service or resolve it
$draftDiff = app(DraftDiff::class);

// Compare two drafts
$draft1 = $post->drafts()->where('version', 1)->first();
$draft2 = $post->drafts()->where('version', 2)->first();

$differences = $draftDiff->compare($draft1, $draft2);

// Example output:
[
    'title' => [
        'type' => 'modified',
        'old' => 'My First Blog Post',
        'new' => 'Updated Blog Post Title'
    ],
    'content' => [
        'type' => 'modified', 
        'old' => 'Initial content',
        'new' => 'This content has been revised and improved.'
    ]
]

// Get human-readable summary
$summary = $draftDiff->getSummary($differences);
echo $summary; // "2 fields modified"

// Format for display
$humanReadable = $draftDiff->formatForHumans($differences);
foreach ($humanReadable as $field => $change) {
    echo "{$field}: {$change}";
}
```

### Compare Draft with Live Model

```php
// Compare current model state with a draft
$latestDraft = $post->drafts()->latest()->first();
$differences = $draftDiff->compareWithModel($post, $latestDraft);

// Compare two different models
$post1 = Post::find(1);
$post2 = Post::find(2);
$modelDifferences = $draftDiff->compareModels($post1, $post2);
```

## ⏪ Version Restoration

### Restoring Previous Versions

```php
// Restore to a specific version
$post->restoreVersion(3); // Restores to version 3

// This will:
// 1. Apply the version 3 data to the model
// 2. Fire a VersionRestored event
// 3. Update the model in the database

// Check what version we're on after restore
echo "Now on version: " . $post->getCurrentVersion();
```

## 🎛️ Configuration Examples

### Auto-Save Configuration

```php
// In your model
class Post extends Model
{
    use HasDrafts;
    
    // Enable auto-save (saves draft on every model update)
    protected $autoSaveDrafts = true;
    
    // Specify which attributes to include in drafts
    protected $draftableAttributes = ['title', 'content', 'excerpt'];
    
    // Include additional data in draft payload
    protected $additionalDraftData = ['meta_description', 'tags'];
}

// With auto-save enabled
$post = Post::find(1);
$post->title = 'New Title';
$post->save(); // Automatically creates a draft
```

### Custom Draft Configuration

```php
// config/laravel-draftable.php
return [
    'table_name' => 'drafts',
    'auto_publish' => false,
    'auto_save' => false,
    'max_versions' => 50, // Keep only last 50 versions
    'cleanup_days' => 90, // Auto-cleanup after 90 days
];
```

## 🚀 Artisan Commands Examples

### List Drafts Command

```bash
# List all drafts
php artisan laravel-draftable:list

# Filter by model type
php artisan laravel-draftable:list --model=Post

# Show only unpublished drafts
php artisan laravel-draftable:list --status=unpublished

# Limit results
php artisan laravel-draftable:list --limit=10

# Combine filters
php artisan laravel-draftable:list --model=Post --status=published --limit=5
```

### Diff Command Examples

```bash
# Compare versions in table format
php artisan laravel-draftable:diff Post 1 --versions=1,2

# Output as JSON
php artisan laravel-draftable:diff Post 1 --versions=1,2 --format=json

# Output as YAML
php artisan laravel-draftable:diff Post 1 --versions=2,3 --format=yaml

# Using full model class name
php artisan laravel-draftable:diff "App\\Models\\Post" 1 --versions=1,2
```

### Cleanup Command Examples

```bash
# Clean up drafts older than 90 days (default)
php artisan laravel-draftable:clear-old

# Custom time period
php artisan laravel-draftable:clear-old --days=30

# Dry run to see what would be deleted
php artisan laravel-draftable:clear-old --days=60 --dry-run

# Force cleanup without confirmation
php artisan laravel-draftable:clear-old --days=180 --force
```

## 🔒 Access Control Examples

### Laravel Policies Integration

```php
// Create a policy
class PostPolicy
{
    public function publishDraft(User $user, Post $post): bool
    {
        return $user->isEditor() || $user->id === $post->user_id;
    }
    
    public function viewDrafts(User $user, Post $post): bool
    {
        return $user->canManageContent() || $user->id === $post->user_id;
    }
}

// In your controller
class PostController extends Controller
{
    public function publishDraft(Post $post)
    {
        $this->authorize('publishDraft', $post);
        
        $post->publishDraft();
        
        return response()->json(['message' => 'Draft published successfully']);
    }
}
```

## 🎯 Advanced Use Cases

### Content Management System

```php
class Article extends Model
{
    use HasDrafts;
    
    protected $autoSaveDrafts = true;
    
    public function scopePublished($query)
    {
        return $query->whereNotNull('published_at');
    }
    
    public function getPublishedVersionAttribute()
    {
        return $this->publishedDrafts()->latest()->first();
    }
    
    public function hasUnpublishedChanges(): bool
    {
        return $this->unpublishedDrafts()->exists();
    }
}

// Usage in CMS
$article = Article::find(1);

// Editor makes changes
$article->update(['title' => 'Breaking News: Updated']);

// Check for pending changes
if ($article->hasUnpublishedChanges()) {
    // Show "Publish Changes" button
    echo "You have unpublished changes. <button>Publish</button>";
}
```

### Workflow with Approvals

```php
use Grazulex\LaravelDraftable\Events\DraftCreated;
use Grazulex\LaravelDraftable\Events\DraftPublished;

// Listen for draft events
class DraftWorkflowListener
{
    public function handleDraftCreated(DraftCreated $event): void
    {
        $draft = $event->draft;
        
        // Notify editors about new draft
        Notification::send(
            User::editors(),
            new NewDraftCreated($draft)
        );
    }
    
    public function handleDraftPublished(DraftPublished $event): void
    {
        $draft = $event->draft;
        
        // Update search index, clear cache, etc.
        SearchIndex::update($draft->draftable);
        Cache::forget("post.{$draft->draftable_id}");
    }
}
```

## 📊 Performance Optimization

### Database Indexes

```php
// The migration includes optimized indexes
Schema::create('drafts', function (Blueprint $table) {
    $table->id();
    $table->morphs('draftable'); // Creates indexes automatically
    $table->json('payload');
    $table->unsignedBigInteger('version')->default(1);
    $table->foreignId('created_by')->nullable()->constrained('users');
    $table->timestamp('published_at')->nullable();
    $table->timestamps();

    // Custom performance indexes
    $table->index(['draftable_type', 'draftable_id', 'version']);
    $table->index('published_at');
    $table->index('created_at');
});
```

### Efficient Queries

```php
// Eager load drafts to avoid N+1 queries
$posts = Post::with(['drafts' => function ($query) {
    $query->latest()->limit(5); // Only load recent drafts
}])->get();

// Get only specific draft data
$draftTitles = $post->drafts()
    ->pluck('payload->title', 'version')
    ->toArray();

// Efficient version checking
$hasRecentDrafts = $post->drafts()
    ->where('created_at', '>', now()->subDays(7))
    ->exists();
```

**🎉 These examples demonstrate the full power of Laravel Draftable in production scenarios!**
```

## Testing Example

```php
use Grazulex\LaravelDraftable\LaravelDraftable;

it('can use the package', function () {
    $package = new LaravelDraftable();
    
    expect($package->version())
        ->toBeString()
        ->not->toBeEmpty();
});
```

## Advanced Usage

More examples will be added as the package grows and features are implemented.
