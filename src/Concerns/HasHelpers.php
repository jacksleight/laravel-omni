<?php

namespace JackSleight\LaravelOmni\Concerns;

use Closure;
use JackSleight\LaravelOmni\Attributes\Helper;
use ReflectionClass;
use ReflectionMethod;

trait HasHelpers
{
    protected function helpers()
    {
        $reflection = new ReflectionClass($this);
        $methods = $reflection->getMethods(ReflectionMethod::IS_PROTECTED);

        return collect($methods)
            ->filter(fn ($method) => $method->getAttributes(Helper::class))
            ->map(fn ($method) => $method->getName())
            ->mapWithKeys(fn ($name) => [$name => Closure::fromCallable([$this, $name])])
            ->all();
    }
}
