<?php

namespace JackSleight\LaravelOmni\Support;

use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Illuminate\View\ComponentAttributeBag;
use JackSleight\LaravelOmni\Component;
use Livewire\Component as LivewireComponent;
use ReflectionClass;
use ReflectionProperty;

class Utils
{
    public static function getPropertyNames(string $class): array
    {
        $reflection = new ReflectionClass($class);
        $properties = $reflection->getProperties(ReflectionProperty::IS_PUBLIC);

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

    public static function getMethodArgumentNames(string $class, string $method): array
    {
        $reflection = new \ReflectionMethod($class, $method);
        $parameters = $reflection->getParameters();

        return collect($parameters)
            ->map(fn ($parameter) => $parameter->getName())
            ->toArray();
    }

    public static function resolveProps(string $class, string $mode, array $data): array
    {
        $names = collect()
            ->merge(Utils::getPropertyNames($class))
            ->merge(Utils::getReservedNames($mode, array_keys($data)))
            ->unique()
            ->all();

        $attributes = $data['attributes'] ?? new ComponentAttributeBag;

        $props = Arr::only(array_merge(
            $data,
            $attributes->onlyProps($names)->getAttributes(),
        ), $names);
        $props['attributes'] = $attributes->exceptProps($names);

        return $props;
    }

    public static function getReservedNames(string $mode, array $names): array
    {
        if ($mode !== Component::LIVEWIRE) {
            return collect($names)
                ->filter(fn ($name) => in_array($name, ['when']))
                ->all();
        }

        return collect($names)
            ->filter(fn ($name) => in_array($name, ['lazy', 'when']) || Str::startsWith($name, ['@', 'wire:model']))
            ->all();
    }

    public static function resolveSlots(array $data = []): array
    {
        $slots = $data['__laravel_slots'];
        $slots['slot'] = $slots['__default'];
        unset($slots['__default']);

        return $slots;
    }

    public static function getTraitNames(string $class): array
    {
        return collect(class_uses($class))
            ->map(fn ($trait) => Str::kebab(class_basename($trait)))
            ->flip()
            ->all();
    }

    public static function callHooks(object $component, string $name, array $data = []): void
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
