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

    protected $info;

    public function __construct()
    {
        $this->info = Omni::prepare(class: static::class);
    }

    protected function data()
    {
        return array_merge(
            $this->all(),
            $this->extractHelperMethods(),
            $this->with(),
        );
    }

    public function render($outer = false)
    {
        if ($outer) {
            return view($this->info->name, $this->data());
        }

        return view()->file($this->info->innerPath, $this->data());
    }
}
