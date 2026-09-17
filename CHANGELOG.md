# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Changed

- `composer.json` now declares `illuminate/console` and `illuminate/database` explicitly (both `^12.0|^13.0`), alongside `illuminate/support`, since the package uses Eloquent, Schema and Artisan commands directly.
- GitHub Actions: `actions/checkout` bumped to v5 and `softprops/action-gh-release` to v2.
- Rector: `DiffDraftsCommand` uses a strict `in_array` check instead of repeated equality comparisons (no behaviour change).

### Fixed

- Removed leftover references to another package in `RELEASES.md`, `CONTRIBUTING.md` and the issue templates.

## [v1.1.0] - 2026-09-17

### Added

- Laravel 13 support (`illuminate/support: ^12.0|^13.0`).
- CI test matrix now covers PHP 8.3 / 8.4 with Laravel 12 (Testbench 10) and Laravel 13 (Testbench 11), in both `prefer-lowest` and `prefer-stable` modes.
- Pint check added to the Code Quality workflow.

### Changed

- Minimum PHP version is now 8.3.
- Development dependencies updated: Pest `^3.8|^4.0`, Pest Laravel plugin `^3.2|^4.0`, Orchestra Testbench `^10.0|^11.0`, Larastan `^3.7`, Pint `^1.25`.
- Release workflow now runs against Laravel 13 / Testbench 11.

### Removed

- Laravel 11 support (end of life).
- Orchestra Testbench 9 support.

### Fixed

- Rector configuration: removed the `strictBooleans` prepared set, which no longer exists in Rector 2.x (its rules are now part of `codeQuality`).

## [v1.0.0] - 2025-08-08

### Added

- Initial release: drafts, versioning and publication workflow for Eloquent models.

[v1.1.0]: https://github.com/Grazulex/laravel-draftable/compare/v1.0.0...v1.1.0
[v1.0.0]: https://github.com/Grazulex/laravel-draftable/releases/tag/v1.0.0
