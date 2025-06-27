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

    protected $__mode = self::LIVEWIRE;

    use HasAttributes;
    use HasHelpers;
    use HasSlot;

    public function __invoke()
    {
        return Omni::mount(static::class, request()->route()->parameters());
    }

    protected function setMode($mode)
    {
        $this->__mode = $mode;
    }

    public function fill($values)
    {
        if ($this->__mode === self::LIVEWIRE) {
            return parent::fill($values);
        }

        if ($values instanceof Model) {
            $values = $values->toArray();
        }

        $names = Utils::getPropertyNames(static::class, $this->__mode === self::STANDARD);

        collect($values)
            ->only($names)
            ->each(function ($value, $name) {
                $this->{$name} = $value;
            });
    }

    public function all()
    {
        $names = Utils::getPropertyNames(static::class, $this->__mode === self::STANDARD);

        return collect($names)
            ->mapWithKeys(function ($name) {
                return [$name => $this->{$name} ?? null];
            })
            ->all();
    }

    protected function with()
    {
        return [];
    }

    public function render()
    {
        $info = Omni::prepare(class: static::class);

        $data = array_merge(
            $this->all(),
            $this->with(),
        );

        if ($this->__mode === self::LIVEWIRE) {
            return view()->file($info->innerPath, $data);
        }

        return view($info->name, $data);
    }
}
