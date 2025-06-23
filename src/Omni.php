<?php

namespace JackSleight\LaravelOmni;

use Illuminate\Support\Facades\Facade;

class Omni extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return Manager::class;
    }
}
