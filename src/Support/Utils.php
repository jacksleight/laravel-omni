<?php

namespace JackSleight\LaravelOmni\Support;

use Illuminate\Support\Arr;
use Illuminate\View\ComponentAttributeBag;
use ReflectionClass;
use ReflectionProperty;

class Utils
{
    public static function getPublicPropertyNames(string $class)
    {
        $reflection = new ReflectionClass($class);
        $properties = $reflection->getProperties(ReflectionProperty::IS_PUBLIC);

        $props = [];
        foreach ($properties as $property) {
            $props[] = $property->getName();
        }

        return $props;
    }

    public static function getMethodArgumentNames(string $class, $method)
    {
        $reflection = new \ReflectionMethod($class, $method);
        $parameters = $reflection->getParameters();

        $args = [];
        foreach ($parameters as $parameter) {
            $args[] = $parameter->getName();
        }

        return $args;
    }

    public static function resolveProps($class, $data = [])
    {
        $names = Utils::getPublicPropertyNames($class);

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
