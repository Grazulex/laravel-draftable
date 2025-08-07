<?php

declare(strict_types=1);

// Exemple de test unitaire - Remplacez ce fichier par vos propres tests

describe('Example Unit Test', function () {
    beforeEach(function () {
        $this->value = 42;
        $this->testClass = new class
        {
            public function getValue(): int
            {
                return 42;
            }

            public function processString(string $input): string
            {
                if (empty($input)) {
                    throw new InvalidArgumentException('Empty input');
                }

                return mb_strtoupper($input);
            }
        };
    });

    it('can perform basic assertions', function () {
        expect($this->value)
            ->toBe(42)
            ->toBeInt()
            ->toBeGreaterThan(0);
    });

    it('can test string operations', function () {
        $string = 'Hello World';

        expect($string)
            ->toBeString()
            ->toContain('Hello')
            ->toStartWith('Hello')
            ->toEndWith('World');
    });

    it('can test arrays', function () {
        $array = [1, 2, 3, 4, 5];

        expect($array)
            ->toBeArray()
            ->toHaveCount(5)
            ->toContain(3);
    });

    it('can test exceptions', function () {
        expect(fn () => throw new InvalidArgumentException('Test exception'))
            ->toThrow(InvalidArgumentException::class, 'Test exception');
    });

    it('can use the fluent tester helper', function () {
        tester($this->testClass)
            ->canBeInstantiated()
            ->hasMethod('getValue')
            ->hasMethod('processString')
            ->methodReturns('getValue', 42)
            ->methodReturns('processString', 'HELLO', ['hello'])
            ->methodThrows('processString', InvalidArgumentException::class, ['']);
    });
})->group('unit', 'example');
