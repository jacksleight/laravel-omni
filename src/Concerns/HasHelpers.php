<?php

namespace JackSleight\LaravelOmni\Concerns;

use Illuminate\Support\Collection;
use Illuminate\View\InvokableComponentVariable;
use JackSleight\LaravelOmni\Attributes\Helper;
use ReflectionClass;
use ReflectionMethod;

trait HasHelpers
{
    protected static $helperCache = [];

    protected function extractHelperMethods()
    {
        $class = get_class($this);

        if (! isset(static::$helperCache[$class])) {
            $reflection = new ReflectionClass($this);

            static::$helperCache[$class] = (new Collection($reflection->getMethods(ReflectionMethod::IS_PROTECTED)))
                ->filter(fn (ReflectionMethod $method) => $method->getAttributes(Helper::class))
                ->map(fn (ReflectionMethod $method) => $method->getName());
        }

        $values = [];

        foreach (static::$helperCache[$class] as $method) {
            $values[$method] = $this->createVariableFromMethod(new ReflectionMethod($this, $method));
        }

        return $values;
    }

    protected function createVariableFromMethod(ReflectionMethod $method)
    {
        return $method->getNumberOfParameters() === 0
            ? $this->createInvokableVariable($method->getName())
            : Closure::fromCallable([$this, $method->getName()]);
    }

    protected function createInvokableVariable(string $method)
    {
        return new InvokableComponentVariable(function () use ($method) {
            return $this->{$method}();
        });
    }
}
