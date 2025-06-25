<?php

namespace JackSleight\LaravelOmni;

use Illuminate\Database\Eloquent\Model;
use JackSleight\LaravelOmni\Concerns\HasAttributes;
use JackSleight\LaravelOmni\Concerns\HasHelpers;
use JackSleight\LaravelOmni\Concerns\HasSlot;
use JackSleight\LaravelOmni\Support\Utils;
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

    public function all()
    {
        $names = Utils::getPropertyNames(static::class);

        return collect($names)
            ->mapWithKeys(function ($name) {
                return [$name => $this->{$name} ?? null];
            })
            ->all();
    }

    public function fill($values)
    {
        if ($values instanceof Model) {
            $values = $values->toArray();
        }

        $names = Utils::getPropertyNames(static::class);

        dump($names);

        collect($values)
            ->only($names)
            ->each(function ($value, $name) {
                $this->{$name} = $value;
            });
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
