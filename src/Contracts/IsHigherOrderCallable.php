<?php

declare(strict_types=1);

namespace Pest\Contracts;

/**
 * @internal
 */
interface IsHigherOrderCallable
{
    public function __invoke();
}
