<?php

namespace JackSleight\LaravelOmni\Concerns;

use Illuminate\View\ComponentSlot;
use Livewire\Attributes\Locked;

trait HasSlot
{
    #[Locked]
    public ?ComponentSlot $slot;
}
