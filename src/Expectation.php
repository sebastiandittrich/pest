<?php

declare(strict_types=1);

namespace Pest;

use BadMethodCallException;
use Closure;
use Pest\Concerns\Extendable;
use Pest\Concerns\Pipeable;
use Pest\Concerns\Retrievable;
use Pest\Exceptions\ExpectationNotFound;
use Pest\Exceptions\InvalidExpectationValue;
use Pest\Expectations\EachExpectation;
use Pest\Expectations\HigherOrderExpectation;
use Pest\Expectations\OppositeExpectation;
use Pest\Support\ExpectationPipeline;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\ExpectationFailedException;

/**
 * @internal
 *
 * @template TValue
 *
 * @property Expectation     $not  Creates the opposite expectation.
 * @property EachExpectation $each Creates an expectation on each element on the traversable value.
 *
 * @mixin Mixins\Expectation<TValue>
 */
final class Expectation
{
    use Extendable;
    use Pipeable;
    use Retrievable;

    /**
     * Creates a new expectation.
     *
     * @param TValue $value
     */
    public function __construct(
        public mixed $value
    ) {
        // ..
    }

    /**
     * Creates a new expectation.
     *
     * @template TAndValue
     *
     * @param TAndValue $value
     *
     * @return self<TAndValue>
     */
    public function and(mixed $value): Expectation
    {
        return $value instanceof static ? $value : new self($value);
    }

    /**
     * Creates a new expectation with the decoded JSON value.
     *
     * @return self<mixed>
     */
    public function json(): Expectation
    {
        if (!is_string($this->value)) {
            InvalidExpectationValue::expected('string');
        }

        return $this->toBeJson()->and(json_decode($this->value, true));
    }

    /**
     * Dump the expectation value and end the script.
     *
     * @return never
     */
    public function dd(mixed ...$arguments): void
    {
        if (function_exists('dd')) {
            dd($this->value, ...$arguments);
        }

        var_dump($this->value);

        exit(1);
    }

    /**
     * Send the expectation value to Ray along with all given arguments.
     *
     * @return self<TValue>
     */
    public function ray(mixed ...$arguments): self
    {
        if (function_exists('ray')) {
            ray($this->value, ...$arguments);
        }

        return $this;
    }

    /**
     * Creates the opposite expectation for the value.
     *
     * @return OppositeExpectation<TValue>
     */
    public function not(): OppositeExpectation
    {
        return new OppositeExpectation($this);
    }

    /**
     * Creates an expectation on each item of the iterable "value".
     *
     * @return EachExpectation<TValue>
     */
    public function each(callable $callback = null): EachExpectation
    {
        if (!is_iterable($this->value)) {
            throw new BadMethodCallException('Expectation value is not iterable.');
        }

        if (is_callable($callback)) {
            foreach ($this->value as $item) {
                $callback(new self($item));
            }
        }

        return new EachExpectation($this);
    }

    /**
     * Allows you to specify a sequential set of expectations for each item in a iterable "value".
     *
     * @template TSequenceValue
     *
     * @param (callable(self<TValue>, self<string|int>): void)|TSequenceValue ...$callbacks
     *
     * @return self<TValue>
     */
    public function sequence(mixed ...$callbacks): Expectation
    {
        if (!is_iterable($this->value)) {
            throw new BadMethodCallException('Expectation value is not iterable.');
        }

        $value          = is_array($this->value) ? $this->value : iterator_to_array($this->value);
        $keys           = array_keys($value);
        $values         = array_values($value);
        $callbacksCount = count($callbacks);

        $index = 0;

        while (count($callbacks) < count($values)) {
            $callbacks[] = $callbacks[$index];
            $index       = $index < count($values) - 1 ? $index + 1 : 0;
        }

        if ($callbacksCount > count($values)) {
            Assert::assertLessThanOrEqual(count($value), count($callbacks));
        }

        foreach ($values as $key => $item) {
            if ($callbacks[$key] instanceof Closure) {
                call_user_func($callbacks[$key], new self($item), new self($keys[$key]));
                continue;
            }

            (new self($item))->toEqual($callbacks[$key]);
        }

        return $this;
    }

    /**
     * If the subject matches one of the given "expressions", the expression callback will run.
     *
     * @template TMatchSubject of array-key
     *
     * @param (callable(): TMatchSubject)|TMatchSubject $subject
     * @param array<TMatchSubject, (callable(self<TValue>): mixed)|TValue> $expressions
     *
     * @return self<TValue>
     */
    public function match(mixed $subject, array $expressions): Expectation
    {
        $subject = $subject instanceof Closure ? $subject() : $subject;

        $matched = false;

        foreach ($expressions as $key => $callback) {
            if ($subject != $key) {
                continue;
            }

            $matched = true;

            if (is_callable($callback)) {
                $callback(new self($this->value));
                continue;
            }

            $this->and($this->value)->toEqual($callback);

            break;
        }

        if ($matched === false) {
            throw new ExpectationFailedException('Unhandled match value.');
        }

        return $this;
    }

    /**
     * Apply the callback if the given "condition" is falsy.
     *
     * @param (callable(): bool)|bool $condition
     * @param callable(Expectation<TValue>): mixed $callback
     *
     * @return self<TValue>
     */
    public function unless(callable|bool $condition, callable $callback): Expectation
    {
        $condition = is_callable($condition)
            ? $condition
            : static function () use ($condition): bool {
                return $condition;
            };

        return $this->when(!$condition(), $callback);
    }

    /**
     * Apply the callback if the given "condition" is truthy.
     *
     * @param (callable(): bool)|bool $condition
     * @param callable(self<TValue>): mixed $callback
     *
     * @return self<TValue>
     */
    public function when(callable|bool $condition, callable $callback): Expectation
    {
        $condition = is_callable($condition)
            ? $condition
            : static function () use ($condition): bool {
                return $condition;
            };

        if ($condition()) {
            $callback($this->and($this->value));
        }

        return $this;
    }

    /**
     * Dynamically calls methods on the class or creates a new higher order expectation.
     *
     * @param array<int, mixed> $parameters
     *
     * @return Expectation<TValue>|HigherOrderExpectation<Expectation<TValue>, TValue>
     */
    public function __call(string $method, array $parameters): Expectation|HigherOrderExpectation
    {
        if (!self::hasMethod($method)) {
            /* @phpstan-ignore-next-line */
            return new HigherOrderExpectation($this, $this->value->$method(...$parameters));
        }

        ExpectationPipeline::for($this->getExpectationClosure($method))
            ->send(...$parameters)
            ->through($this->pipes($method, $this, Expectation::class))
            ->run();

        return $this;
    }

    /**
     * Creates a new expectation closure from the given name.
     *
     * @throws ExpectationNotFound
     */
    private function getExpectationClosure(string $name): Closure
    {
        if (method_exists(Mixins\Expectation::class, $name)) {
            //@phpstan-ignore-next-line
            return Closure::fromCallable([new Mixins\Expectation($this->value), $name]);
        }

        if (self::hasExtend($name)) {
            $extend = self::$extends[$name]->bindTo($this, Expectation::class);

            if ($extend != false) {
                return $extend;
            }
        }

        throw ExpectationNotFound::fromName($name);
    }

    /**
     * Dynamically calls methods on the class without any arguments or creates a new higher order expectation.
     *
     * @return Expectation<TValue>|OppositeExpectation<TValue>|EachExpectation<TValue>|HigherOrderExpectation<Expectation<TValue>, TValue|null>|TValue
     */
    public function __get(string $name)
    {
        if (!self::hasMethod($name)) {
            /* @phpstan-ignore-next-line */
            return new HigherOrderExpectation($this, $this->retrieve($name, $this->value));
        }

        /* @phpstan-ignore-next-line */
        return $this->{$name}();
    }

    /**
     * Checks if the given expectation method exists.
     */
    public static function hasMethod(string $name): bool
    {
        return method_exists(self::class, $name)
            || method_exists(Mixins\Expectation::class, $name)
            || self::hasExtend($name);
    }
}
