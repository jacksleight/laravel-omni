<?php

namespace JackSleight\LaravelOmni\Concerns;

use Illuminate\View\ComponentAttributeBag;
use Illuminate\View\View;
use Livewire\Attributes\Locked;

trait HasAttributes
{
    #[Locked]
    public ?ComponentAttributeBag $attrs;

    public function mountHasAttributes(ComponentAttributeBag $attributes): void
    {
        $this->attrs = $attributes;
    }

    public function renderingHasAttributes(View $view): void
    {
        $view['attributes'] = $view['attrs'];
        unset($view['attrs']);
    }
}
