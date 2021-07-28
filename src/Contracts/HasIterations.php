<?php

declare(strict_types=1);

namespace Pest\Contracts;

if (interface_exists(\NunoMaduro\Collision\Contracts\Adapters\Phpunit\HasIterations::class)) {
    /**
     * @internal
     */
    interface HasIterations extends \NunoMaduro\Collision\Contracts\Adapters\Phpunit\HasIterations
    {
    }
} else {
    /**
     * @internal
     */
    interface HasIterations
    {
    }
}
