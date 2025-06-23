<?php

namespace JackSleight\LaravelOmni\Synths;

use Illuminate\View\ComponentAttributeBag as BaseComponentAttributeBag;
use Livewire\Mechanisms\HandleComponents\Synthesizers\Synth;

class ComponentAttributeBag extends Synth
{
    public static string $key = 'component-attribute-bag';

    public static function match($target): bool
    {
        return $target instanceof BaseComponentAttributeBag;
    }

    public function dehydrate($target): array
    {
        return [$target->all(), []];
    }

    public function hydrate($value): BaseComponentAttributeBag
    {
        return new BaseComponentAttributeBag($value);
    }

    public function get(&$target, $key)
    {
        return $target[$key];
    }

    public function set(&$target, $key, $value)
    {
        $target[$key] = $value;
    }
}
