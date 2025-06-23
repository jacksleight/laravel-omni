<?php

namespace JackSleight\LaravelOmni\Synths;

use Illuminate\View\ComponentSlot as BaseComponentSlot;
use Livewire\Mechanisms\HandleComponents\Synthesizers\Synth;

class ComponentSlot extends Synth
{
    public static string $key = 'component-slot';

    public static function match($target): bool
    {
        return $target instanceof BaseComponentSlot;
    }

    public function dehydrate($target): array
    {
        return [$target->toHtml(), []];
    }

    public function hydrate($value): BaseComponentSlot
    {
        return new BaseComponentSlot($value);
    }
}
