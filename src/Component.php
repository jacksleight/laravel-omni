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

    protected $__mode = self::LIVEWIRE;

    use HasAttributes;
    use HasHelpers;
    use HasSlot;

    public function __invoke()
    {
        return Omni::request(request(), static::class);
    }

    protected function setMode($mode)
    {
        $this->__mode = $mode;
    }

    protected function isStandard()
    {
        return $this->__mode === self::STANDARD;
    }

    protected function isLivewire()
    {
        return $this->__mode === self::LIVEWIRE;
    }

    protected function with()
    {
        return [];
    }

    public function render()
    {
        $info = Omni::lookup(class: static::class);

        $data = array_merge(
            $this->all(),
            $this->with(),
        );

        if ($this->isLivewire()) {
            return view()->file($info->innerPath, $data);
        }

        return view()->file($info->outerPath, $data);
    }
}
