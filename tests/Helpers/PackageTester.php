<?php

declare(strict_types=1);

/**
 * Helper pour simplifier les tests du package.
 *
 * Cette classe fournit des méthodes utilitaires pour les tests,
 * suivant le pattern Fluent API pour une meilleure lisibilité.
 */
final class PackageTester
{
    public function __construct(
        private mixed $subject = null
    ) {
        //
    }

    public static function for(mixed $subject): self
    {
        return new self($subject);
    }

    public function test(mixed $subject): self
    {
        $this->subject = $subject;
        return $this;
    }

    public function canBeInstantiated(): self
    {
        expect($this->subject)
            ->not->toBeNull()
            ->toBeObject();
        return $this;
    }

    public function hasMethod(string $method): self
    {
        expect($this->subject)
            ->toBeObject()
            ->and(method_exists($this->subject, $method))
            ->toBeTrue();
        return $this;
    }

    public function implements(string $interface): self
    {
        expect($this->subject)
            ->toBeInstanceOf($interface);
        return $this;
    }

    public function methodReturns(string $method, mixed $expected, array $args = []): self
    {
        $result = $this->subject->{$method}(...$args);
        expect($result)->toBe($expected);
        return $this;
    }

    public function methodThrows(string $method, string $exception, array $args = []): self
    {
        expect(fn () => $this->subject->{$method}(...$args))
            ->toThrow($exception);
        return $this;
    }
}
