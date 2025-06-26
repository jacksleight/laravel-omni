<?php

namespace JackSleight\LaravelOmni\Concerns;

use Closure;

trait HasHelpers
{
    protected function helper($name)
    {
        return Closure::fromCallable([$this, $name]);
    }
}
