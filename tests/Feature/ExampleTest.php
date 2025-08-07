<?php

declare(strict_types=1);

// Exemple de test de fonctionnalité - Remplacez ce fichier par vos propres tests

describe('Example Feature Test', function () {
    beforeEach(function () {
        // Configuration pour les tests de fonctionnalité
        // Exemple: base de données, mocks, etc.
    });

    it('can test Laravel application features', function () {
        // Ici vous testeriez les fonctionnalités spécifiques à Laravel
        // comme les requêtes HTTP, les interactions avec la base de données, etc.

        expect(true)->toBeTrue();
    });

    it('can test package integration with Laravel', function () {
        // Testez comment votre package s'intègre avec Laravel

        expect(config())->not->toBeNull();
        expect(app())->not->toBeNull();
    });

    it('can test service provider registration', function () {
        // Testez que votre service provider est correctement enregistré

        expect(app()->getLoadedProviders())
            ->toHaveKey('Grazulex\LaravelDraftable\LaravelDraftableServiceProvider');
    });

    it('can test configuration merging', function () {
        // Testez que la configuration par défaut est bien chargée
        // Remplacez 'laravel-draftable' par le nom réel de votre package

        expect(config('app.providers'))
            ->toBeArray();
    });
})->group('feature', 'example');
