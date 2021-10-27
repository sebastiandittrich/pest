<?php

declare(strict_types=1);

namespace Pest\Logging\TeamCity;

use Pest\Contracts\HasIterations;
use PHPUnit\Framework\Test;
use PHPUnit\Framework\TestResult;

final class PestAwareTest implements Test
{
    /**
     * The underlying test instance that will be deferred to.
     *
     * @var Test
     */
    private $underlyingTest;

    public function __construct(Test $underlyingTest)
    {
        $this->underlyingTest = $underlyingTest;
    }

    public function count(): int
    {
        return $this->underlyingTest->count();
    }

    public function run(TestResult $result = null): TestResult
    {
        return $this->underlyingTest->run($result);
    }

    public function getName(): string
    {
        if (!$this->underlyingTest instanceof HasIterations) {
            /* @phpstan-ignore-next-line  */
            return $this->underlyingTest->getName();
        }

        if (is_null($iteration = $this->underlyingTest->getIteration())) {
            /* @phpstan-ignore-next-line  */
            return $this->underlyingTest->getName();
        }

        /* @phpstan-ignore-next-line  */
        return sprintf('%s ⟲ %s of %s', $this->underlyingTest->getName(), $iteration->iteration, $iteration->total);
    }

    /**
     * @return mixed
     */
    public function __get(string $name)
    {
        /* @phpstan-ignore-next-line  */
        return $this->underlyingTest->$name;
    }

    /**
     * @param array<int, mixed> $arguments
     *
     * @return mixed
     */
    public function __call(string $name, array $arguments)
    {
        /* @phpstan-ignore-next-line  */
        return call_user_func_array([$this->underlyingTest, $name], $arguments);
    }
}
