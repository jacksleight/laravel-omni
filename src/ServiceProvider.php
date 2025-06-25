<?php

namespace JackSleight\LaravelOmni;

use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider as BaseServiceProvider;
use Illuminate\View\AnonymousComponent;
use Livewire\Livewire;

class ServiceProvider extends BaseServiceProvider
{
    public function register()
    {
        $this->app->singleton(Manager::class);
    }

    public function boot()
    {
        $this
            ->bootAutoload()
            ->bootComponents()
            ->bootSynths()
            ->bootMacros()
            ->bootPaths();
    }

    protected function bootAutoload()
    {
        spl_autoload_register([Omni::class, 'autoload']);

        return $this;
    }

    protected function bootComponents()
    {
        Blade::prepareStringsForCompilationUsing([Omni::class, 'decompose']);

        AnonymousComponent::resolveComponentsUsing([Omni::class, 'resolveStandard']);
        Livewire::resolveMissingComponent([Omni::class, 'resolveLivewire']);

        return $this;
    }

    protected function bootMacros()
    {
        View::macro('omni', [Omni::class, 'mount']);
        Router::macro('omni', [Omni::class, 'route']);

        return $this;
    }

    protected function bootSynths()
    {
        Livewire::propertySynthesizer(Synths\ComponentAttributeBag::class);
        Livewire::propertySynthesizer(Synths\ComponentSlot::class);

        return $this;
    }

    protected function bootPaths()
    {
        Blade::anonymousComponentPath(resource_path('views'));

        return $this;
    }
}
