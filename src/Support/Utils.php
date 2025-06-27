<?php

namespace JackSleight\LaravelOmni\Support;

use Illuminate\Support\Arr;
use Illuminate\View\ComponentAttributeBag;
use JackSleight\LaravelOmni\Component;
use Livewire\Component as LivewireComponent;
use ReflectionClass;
use ReflectionProperty;

class Utils
{
    public static function getPropertyNames(string $class, $protected)
    {
        $visibility = $protected
            ? (ReflectionProperty::IS_PUBLIC | ReflectionProperty::IS_PROTECTED)
            : ReflectionProperty::IS_PUBLIC;

        $reflection = new ReflectionClass($class);
        $properties = $reflection->getProperties($visibility);

        $ignoreClasses = [
            Component::class,
            LivewireComponent::class,
        ];

        return collect($properties)
            ->reject(fn ($property) => in_array($property->getDeclaringClass()->getName(), $ignoreClasses))
            ->reject(fn ($property) => $property->isStatic())
            ->map(fn ($property) => $property->getName())
            ->merge(['attrs', 'slot'])
            ->all();
    }

    public static function getMethodArgumentNames(string $class, $method)
    {
        $reflection = new \ReflectionMethod($class, $method);
        $parameters = $reflection->getParameters();

        return collect($parameters)
            ->map(fn ($parameter) => $parameter->getName())
            ->toArray();
    }

    public static function resolveProps($class, $data)
    {
        $names = Utils::getPropertyNames($class, true);

        $attributes = $data['attributes'] ?? new ComponentAttributeBag;

        $props = Arr::only(array_merge(
            $data,
            $attributes->onlyProps($names)->getAttributes(),
        ), $names);
        $props['attributes'] = $attributes->exceptProps($names);

        return $props;
    }

    public static function resolveSlots($data = [])
    {
        $slots = $data['__laravel_slots'];
        $slots['slot'] = $slots['__default'];
        unset($slots['__default']);

        return $slots;
    }

    public static function callHooks($component, $name, $data = [])
    {
        if (method_exists($component, $name)) {
            $args = Arr::only($data, static::getMethodArgumentNames($component::class, $name));
            app()->call([$component, $name], $args);
        }
        foreach (class_uses_recursive($component) as $trait) {
            $method = $name.class_basename($trait);
            if (method_exists($component, $method)) {
                $args = Arr::only($data, static::getMethodArgumentNames($component::class, $method));
                app()->call([$component, $method], $args);
            }
        }
    }
}
