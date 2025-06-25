<?php

namespace JackSleight\LaravelOmni\Concerns;

use Closure;
use JackSleight\LaravelOmni\Component;
use Livewire\Component as LivewireComponent;
use ReflectionClass;
use ReflectionMethod;

trait HasHelpers
{
    protected function helpers()
    {
        $reflection = new ReflectionClass($this);
        $methods = $reflection->getMethods(ReflectionMethod::IS_PROTECTED);

        $ignoreClasses = [
            Component::class,
            LivewireComponent::class,
        ];

        return collect($methods)
            ->filter(fn ($property) => ! in_array($property->getDeclaringClass()->getName(), $ignoreClasses))
            ->map(fn ($method) => $method->getName())
            ->mapWithKeys(fn ($name) => [$name => Closure::fromCallable([$this, $name])])
            ->except(['with'])
            ->all();
    }
}
