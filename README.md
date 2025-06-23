# Omni

Omni is a Laravel package and Vite plugin for building universal single-file components.

The core goals of Omni are:

- A single API for defining components (with or without Livewire features)
- A single syntax for including and mounting components
- A single directory structure for organising components
- A single file for all component concerns (logic, template, scripts and styles)

And all Omni components can:

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

## Defining Components

Omni components can live in any view directory, and a basic component looks like this:

```blade
<?php 
namespace App\Omni;

use JackSleight\LaravelOmni\Component;

class Counter extends Component
{
    public int $count = 0;
} ?>

<template render>
    <div>
        {{ $count }}
    </div>
</template>

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
    <template wire:render> {{-- Make it Livewire! --}}
        <div>
            {{ $count }}
            <button wire:click="increment">+</button>
        </div>
    </template>
</x-app.layout>
```

### Class and Namespace

An Omni component's class and namespace maps directly to the component's prefix, name and path. They must all match, and the namespace must include `Omni`. The part before `Omni` is the prefix, and the part after is the component name, for example:

```
Name:  image
Path:  resources/views/image.blade.php
Class: App\Omni\Image

Name:  my-package::events.stat-counter
Path:  vendor/my-package/resources/views/events/stat-counter.blade.php
Class: MyPackage\Omni\Events\StatCounter
```

### Lifecycle

Non-Livewire components support `mount` and `rendering` lifecycle hooks:

```php
protected function mount($value)
{
    // ...
}

protected function rendering($view)
{
    // ...
}
```

Livewire components run through the usual [Livewire lifecycle](https://livewire.laravel.com/docs/lifecycle-hooks).

### With

All public properties and helper functions will be provided to the template as usual. To pass additional variables to the template use the `with` method:

```php
protected function with()
{
    return [
        // ...
    ];
}
```

### Helpers

Public methods on standard Blade components are helpers you can call from the template, but public methods on Livewire components are actions you can call from the client. Due to this conflict Omni components work a bit differently. To add helper functions to an Omni component the method should be defined as `protected` with a `#[Helper]` attribute:

```php
use JackSleight\LaravelOmni\Attributes\Helper;

#[Helper]
protected function random()
{
    // ...
}
```

## Mounting Components

### Blade Templates

### Controllers

### Routes

## Component Execution

Omni components will execute in one of three modes depending on the type you declare and the template structure.

* **Standard Mode**  
  All components that declare a `render` template execute in standard mode, with no Livewire features. They support `mount` and `rendering` lifecycle hooks.

* **Livewire Mode**  
  Components that declare a `wire:render` template and have no code outside of the `<template>` tag execute in Livewire mode, with full Livewire features. They run through the usual [Livewire lifecycle](https://livewire.laravel.com/docs/lifecycle-hooks).

* **Combined Mode**  
  Components that declare a `wire:render` template and have code outside of the `<template>` tag execute in combined mode. Combined components are actually two instances of the same component. The part of the template outside the `<template>` tag is executed in standard mode, and then the part of the template inside the `<template>` tag is executed in Livewire mode.

## Credits

While Omni does not depend on [Livewire Volt](https://livewire.laravel.com/docs/volt), it is obviously heavily inspired by Volt's single-file structure and class-based syntax. This package would not exist if it wasn't for Volt, so a huge thanks to the Volt team. ❤️