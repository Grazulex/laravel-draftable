# Artisan Commands

Laravel Draftable includes powerful Artisan commands to help you manage drafts efficiently from the command line.

## 📝 Commands Overview

| Command | Description | Test Coverage |
|---------|-------------|---------------|
| `laravel-draftable:list` | List and filter drafts | ✅ 13/13 tests |
| `laravel-draftable:diff` | Compare draft versions | ✅ 14/14 tests |
| `laravel-draftable:clear-old` | Clean up old drafts | ✅ 14/14 tests |

## 📋 List Drafts Command

Display drafts with powerful filtering and formatting options.

### Basic Usage

```bash
# List all drafts
php artisan laravel-draftable:list

# Example output:
┌─────────┬──────────┬─────────────┬─────────┬─────────────────────┬─────────────────────┐
│ ID      │ Model    │ Model ID    │ Version │ Status              │ Created             │
├─────────┼──────────┼─────────────┼─────────┼─────────────────────┼─────────────────────┤
│ 1       │ Post     │ 1           │ 1       │ Published           │ 2025-08-07 10:30:15 │
│ 2       │ Post     │ 1           │ 2       │ Unpublished         │ 2025-08-07 11:45:22 │
│ 3       │ Post     │ 2           │ 1       │ Published           │ 2025-08-07 12:15:08 │
└─────────┴──────────┴─────────────┴─────────┴─────────────────────┴─────────────────────┘
```

### Filtering Options

```bash
# Filter by model type
php artisan laravel-draftable:list --model=Post
php artisan laravel-draftable:list --model=User

# Filter by status
php artisan laravel-draftable:list --status=published
php artisan laravel-draftable:list --status=unpublished

# Limit results
php artisan laravel-draftable:list --limit=10

# Combine multiple filters
php artisan laravel-draftable:list --model=Post --status=unpublished --limit=5
```

### Advanced Filtering

```bash
# Use partial model names (searches within model class names)
php artisan laravel-draftable:list --model=Blog  # Matches BlogPost, BlogCategory, etc.

# Set limit to 0 to show all results (ignores default pagination)
php artisan laravel-draftable:list --limit=0
```

## 🔍 Diff Command

Compare different versions of drafts to see exactly what changed.

### Basic Usage

```bash
# Compare two versions of a model
php artisan laravel-draftable:diff Post 1 --versions=1,2

# Example table output:
┌─────────┬─────────────┬─────────────┬─────────────────────────────────┐
│ Field   │ Change Type │ Old Value   │ New Value                       │
├─────────┼─────────────┼─────────────┼─────────────────────────────────┤
│ title   │ Modified    │ First Post  │ Updated First Post              │
│ content │ Modified    │ Initial...  │ This content has been updated   │
│ status  │ Added       │             │ published                       │
└─────────┴─────────────┴─────────────┴─────────────────────────────────┘
```

### Output Formats

```bash
# JSON format
php artisan laravel-draftable:diff Post 1 --versions=1,2 --format=json

# Example JSON output:
{
    "title": {
        "type": "modified",
        "old": "First Post",
        "new": "Updated First Post"
    },
    "content": {
        "type": "modified", 
        "old": "Initial content",
        "new": "This content has been updated"
    }
}

# YAML format
php artisan laravel-draftable:diff Post 1 --versions=1,2 --format=yaml

# Example YAML output:
title:
  type: modified
  old: "First Post"
  new: "Updated First Post"
content:
  type: modified
  old: "Initial content"
  new: "This content has been updated"
```

### Model Resolution

```bash
# Using simple model name (searches in App\Models namespace)
php artisan laravel-draftable:diff Post 1 --versions=1,2

# Using full model class name
php artisan laravel-draftable:diff "App\\Models\\BlogPost" 1 --versions=1,2

# Works with any namespace
php artisan laravel-draftable:diff "Custom\\Namespace\\Article" 1 --versions=1,2
```

### Error Handling

The command provides helpful error messages:

```bash
# Non-existent model
$ php artisan laravel-draftable:diff NonExistent 1 --versions=1,2
Error: Model class not found: NonExistent

# Non-existent model ID
$ php artisan laravel-draftable:diff Post 999 --versions=1,2  
Error: Model not found: Post with ID 999

# Non-existent versions
$ php artisan laravel-draftable:diff Post 1 --versions=999,1000
Error: Version not found: 999 for Post ID 1
```

## 🧹 Clear Old Drafts Command

Clean up old drafts to maintain database performance and storage efficiency.

### Basic Usage

```bash
# Clean up drafts older than 90 days (default)
php artisan laravel-draftable:clear-old

# Example output:
Found 15 drafts older than 90 days (before 2025-05-09)

Examples of drafts to be deleted:
- Draft #1: Post #1 (version 1) - created 2025-01-15
- Draft #2: Post #2 (version 1) - created 2025-02-20
- Draft #3: User #5 (version 2) - created 2025-03-10
...

Do you want to continue? (yes/no) [no]: yes
✓ Successfully deleted 15 old drafts.
```

### Custom Time Periods

```bash
# Clean up drafts older than 30 days
php artisan laravel-draftable:clear-old --days=30

# Clean up very old drafts (1 year+)
php artisan laravel-draftable:clear-old --days=365

# Clean up recent drafts (be careful!)
php artisan laravel-draftable:clear-old --days=7
```

### Safety Features

```bash
# Dry run - see what would be deleted without actually deleting
php artisan laravel-draftable:clear-old --days=60 --dry-run

# Example dry run output:
DRY RUN: Found 8 drafts older than 60 days (before 2025-06-08)

The following drafts would be deleted:
- Draft #12: Post #3 (version 2) - created 2025-04-15
- Draft #13: Post #4 (version 1) - created 2025-05-01
...

Total: 8 drafts would be deleted.
No drafts were actually deleted (dry run mode).

# Force deletion without confirmation (use in scripts)
php artisan laravel-draftable:clear-old --days=90 --force
```

### Edge Cases Handled

```bash
# No old drafts found
$ php artisan laravel-draftable:clear-old --days=30
No drafts found older than 30 days.

# Invalid days parameter
$ php artisan laravel-draftable:clear-old --days=-5
Error: Days parameter must be a positive integer.

$ php artisan laravel-draftable:clear-old --days=abc
Error: Days parameter must be a valid integer.

# User cancels operation
$ php artisan laravel-draftable:clear-old --days=30
Found 5 drafts older than 30 days...
Do you want to continue? (yes/no) [no]: no
Operation cancelled.
```

## 🔧 Command Integration

### Using in Scripts

```bash
#!/bin/bash
# Automated cleanup script

echo "Running daily draft maintenance..."

# Clean up drafts older than 30 days
php artisan laravel-draftable:clear-old --days=30 --force

# Generate draft summary report
php artisan laravel-draftable:list --format=json > /tmp/draft-report.json

echo "Maintenance completed."
```

### Scheduling in Laravel

```php
// app/Console/Kernel.php
protected function schedule(Schedule $schedule): void
{
    // Clean up old drafts weekly
    $schedule->command('laravel-draftable:clear-old --days=90 --force')
             ->weekly()
             ->sundays()
             ->at('02:00');
             
    // Generate daily draft reports
    $schedule->command('laravel-draftable:list --limit=100')
             ->daily()
             ->appendOutputTo('/var/log/laravel/draft-reports.log');
}
```

## ⚡ Performance Tips

### Efficient Usage

```bash
# When working with large datasets, use limits
php artisan laravel-draftable:list --limit=50

# For automated processing, use JSON format
php artisan laravel-draftable:list --format=json | jq '.[] | select(.status == "unpublished")'

# Regular cleanup prevents performance issues
php artisan laravel-draftable:clear-old --days=30 --force
```

### Monitoring

```bash
# Check draft counts by model
php artisan laravel-draftable:list | awk '{print $2}' | sort | uniq -c

# Find models with many versions
php artisan laravel-draftable:list --format=json | jq 'group_by(.model_id) | .[] | {model_id: .[0].model_id, count: length}'
```

## 🎯 Testing Commands

All commands are thoroughly tested with 100% coverage:

- **List Command**: 13 comprehensive tests covering filtering, pagination, error handling
- **Diff Command**: 14 tests covering all output formats, error scenarios, and edge cases  
- **Clear Command**: 14 tests covering dry runs, confirmations, edge cases, and error handling

**🚀 These production-ready commands provide everything you need for professional draft management!**
