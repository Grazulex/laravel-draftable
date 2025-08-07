<?php

declare(strict_types=1);

use Grazulex\LaravelDraftable\LaravelDraftable;

describe('LaravelDraftable', function () {
    beforeEach(function () {
        $this->package = new LaravelDraftable();
    });

    it('can be instantiated', function () {
        expect($this->package)
            ->toBeInstanceOf(LaravelDraftable::class);
    });

    it('has a version', function () {
        expect($this->package->version())
            ->toBeString()
            ->not->toBeEmpty();
    });

    it('can check if enabled', function () {
        expect($this->package->isEnabled())
            ->toBeBool();
    });

    it('can process example method', function () {
        $result = $this->package->exampleMethod('hello');

        expect($result)
            ->toBe('Processed: hello');
    });

    it('throws exception for empty input', function () {
        expect(fn () => $this->package->exampleMethod(''))
            ->toThrow(InvalidArgumentException::class);
    });
})->group('unit');
