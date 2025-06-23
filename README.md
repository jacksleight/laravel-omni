# Omni

Omni is a Laravel package and Vite plugin for building universal single-file components.

The core goals of Omni are:

- A single API for defining components.
- A single syntax for calling components.
- A single structure for organising components.
- A single file for all component parts (logic, template, scripts and styles).

All Omni components can:

- Be declared as standard or Livewire components
- Be mounted to a route as a full-page component  
- Be mounted from a controller
- Be rendered in a template using `<x-component>` syntax  
- Use layouts, slots, and attribute bags  
- Define and typehint their properties
- Define helper functions that can be used in templates
- Include JS and CSS that’s bundled by Vite
- Extend other Omni components
- Live in any view directory

## Component Structure

A basic Omni component looks like this:

```blade
<?php 
namespace App\Omni;

use JackSleight\LaravelOmni\Component;

class Counter extends Component
{
    public int $count = 0;
} ?>

<x-app.layout>
    <template standard>
        <div>
            {{ $count }}
        </div>
    </template>
</x-app.layout>

<style bundle>
    /* ... */
</style>

<script bundle>
    /* ... */
</script>
```

Or if you need Livewire features and a layout:

```blade
<?php 
namespace App\Omni;

use JackSleight\LaravelOmni\Component;

class Counter extends Component
{
    public int $count = 0;

    public function increment()
    {
        $this->count++;
    }
} ?>

<x-app.layout>
    <template livewire> 
        <div>
            {{ $count }}
            <button wire:click="increment">+</button>
        </div>
    </template>
</x-app.layout>
```

## Component Execution

Omni components will execute in one of three modes depending on the type you declare and the template structure.

### Standard Mode

All components that declare a `standard` template execute in standard mode, with no Livewire features. They support `mount` and `rendering` lifecycle hooks.

### Livewire Mode

Components that declare a `livewire` template and have no code outside of the `<template>` tag execute in Livewire mode, with full Livewire features. They run through the usual [Livewire lifecycle](https://livewire.laravel.com/docs/lifecycle-hooks).

### Combined Mode

Components that declare a `livewire` template and code outside of the `<template>` tag execute in combined mode. Combined components are actually two instances of the same component. The part of the template outside the `<template>` tag is executed in standard mode, and then the part of the template inside the `<template>` tag is executed in Livewire mode.

## Component Features

## With

All public properties and helper functions will be provided to the template as usual. To pass additional variables to the template use the `with` method:

```php
protected function with()
{
    return [
        'labels' => // ...
    ];
}
```

## Helpers

Public methods on standard Blade components are helpers you can call from the template, but public methods on Livewire components are actions you can call from the client. Due to this conflict Omni components work a bit differently. To add helper functions to an Omni component the method should be defined as `protected` with a `#[Helper]` attribute:

```
use JackSleight\LaravelOmni\Attributes\Helper;

#[Helper]
protected function random()
{
    return rand(100, 999);
}
```