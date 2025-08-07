# Laravel Draftable

**Laravel Draftable** is a package that adds **drafts**, **versioning**, and **publication flow** to any Eloquent model in a Laravel application.

[![Latest Version on Packagist](https://img.shields.io/packagist/v/grazulex/laravel-draftable.svg?style=flat-square)](https://packagist.org/packages/grazulex/laravel-draftable)
[![Total Downloads](https://img.shields.io/packagist/dt/grazulex/laravel-draftable.svg?style=flat-square)](https://packagist.org/packages/grazulex/laravel-draftable)
[![GitHub Tests Action Status](https://img.shields.io/github/actions/workflow/status/grazulex/laravel-draftable/tests.yml?branch=main&label=tests&style=flat-square)](https://github.com/grazulex/laravel-draftable/actions?query=workflow%3Atests+branch%3Amain)
[![GitHub Code Style Action Status](https://img.shields.io/github/actions/workflow/status/grazulex/laravel-draftable/pint.yml?branch=main&label=code%20style&style=flat-square)](https://github.com/grazulex/laravel-draftable/actions?query=workflow%3Apint+branch%3Amain)

---

## ✨ Features

- Save any model as a **draft**, without publishing changes immediately
- Maintain a **version history** of all changes
- Compare versions to see what changed (`diff`)
- **Restore** a previous version at any time
- Automatically or manually **publish** drafts
- Support for multiple storage strategies (same table or separate `drafts` table)
- Preview a draft without making it live
- Integration with Laravel policies for access control
- Support for soft deletes and timestamps

---

## 📋 Requirements

- PHP ^8.3
- Laravel ^11.44.2

---

## 📦 Installation

```bash
composer require grazulex/laravel-draftable
```

---

## 🧪 Usage

### Add the trait to your model

```php
use Grazulex\LaravelDraftable\Traits\HasDrafts;

class Post extends Model
{
    use HasDrafts;
}
```

### Save a draft

```php
$post->title = 'Updated title';
$post->saveDraft();
```

### Publish the latest draft

```php
$post->publishDraft();
```

### List all drafts

```php
$drafts = $post->drafts;
```

### Compare two versions

```php
use Grazulex\LaravelDraftable\Services\DraftDiff;

$diff = DraftDiff::compare($v1, $v2);
```

### Restore a previous version

```php
$post->restoreVersion($versionId);
```

---

## 🗃️ Recommended Draft Table Migration

The package includes a migration, but you can customize it:

```php
Schema::create('drafts', function (Blueprint $table) {
    $table->id();
    $table->morphs('draftable');
    $table->json('payload');
    $table->unsignedBigInteger('version')->default(1);
    $table->foreignId('created_by')->nullable()->constrained('users');
    $table->timestamp('published_at')->nullable();
    $table->timestamps();
});
```

---

## 📊 Optional Diff Output

```php
[
  'title' => ['old' => 'Old title', 'new' => 'New title'],
  'content' => ['old' => 'Lorem...', 'new' => 'Updated content']
]
```

---

## 🔐 Access Control

Draftable supports Laravel Policies. You can restrict publishing to specific roles:

```php
Gate::define('publish', function ($user, $post) {
    return $user->isEditorOrAdmin();
});
```

---

## 🧩 Optional Artisan Commands

```bash
php artisan draftable:clear-old --days=90
php artisan draftable:list
php artisan draftable:diff Post 123 125
```

---

## 📚 Documentation

For detailed documentation, examples, and advanced usage, visit our [Wiki](https://github.com/Grazulex/laravel-draftable/wiki).

---

## 🔮 Roadmap Ideas

- Webhook support on publish
- Git-style delta storage engine
- Moderation workflow with approval states

---

## 🤝 Contributing

Please see [CONTRIBUTING](CONTRIBUTING.md) for details.

## 🔒 Security

If you discover any security related issues, please email jms@grazulex.be instead of using the issue tracker.

## 📄 License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.

---

MIT © [Grazulex](https://github.com/Grazulex)

---

**Built with ❤️ following SOLID principles and clean code practices.**
