<?php

namespace JackSleight\LaravelOmni;

use Illuminate\Routing\Controllers\HasMiddleware;
use JackSleight\LaravelOmni\Concerns\HasAttributes;
use JackSleight\LaravelOmni\Concerns\HasHelpers;
use JackSleight\LaravelOmni\Concerns\HasSlot;
use Livewire\Component as LivewireComponent;

class Component extends LivewireComponent implements HasMiddleware
{
    const STANDARD = 'standard';

    const LIVEWIRE = 'livewire';

    const COMBINED = 'combined';

    use HasAttributes;
    use HasHelpers;
    use HasSlot;

    public static function middleware()
    {
        return fn ($request, $next) => Omni::request($request, $next, static::class);
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
            ['__omni' => $this],
        );

        if ($this->getId()) {
            return view()->file($info->innerPath, $data);
        }

        return view()->file($info->outerPath, $data);
    }
}
