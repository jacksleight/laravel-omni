<?php

namespace JackSleight\LaravelOmni;

use JackSleight\LaravelOmni\Concerns\HasAttributes;
use JackSleight\LaravelOmni\Concerns\HasHelpers;
use JackSleight\LaravelOmni\Concerns\HasSlot;
use Livewire\Component as LivewireComponent;

class Component extends LivewireComponent
{
    const STANDARD = 'standard';

    const LIVEWIRE = 'livewire';

    const COMBINED = 'combined';

    use HasAttributes;
    use HasHelpers;
    use HasSlot;

    public function __invoke()
    {
        return Omni::mount(static::class, request()->route()->parameters());
    }

    protected function data()
    {
        return array_merge(
            $this->all(),
            $this->helpers(),
            $this->with(),
        );
    }

    protected function with()
    {
        return [];
    }

    public function render($outer = false)
    {
        $info = Omni::prepare(class: static::class);

        if ($outer) {
            return view($info->name, $this->data());
        }

        return view()->file($info->innerPath, $this->data());
    }
}
