# Omni

Omni is a Laravel package and Vite plugin for building universal single-file components.

The core goals of Omni are:

- A single API for defining components (with or without Livewire features)
- A single syntax for including and mounting components
- A single directory structure for organising components
- A single file for all component concerns (logic, template, scripts and styles)

All Omni components can:

- Be declared as standard or Livewire components
- Be mounted to a route as a full-page component  
- Be rendered from a controller
- Be rendered in a template using `<x-component>` syntax  
- Use layouts, slots, and attribute bags
- Define helper functions that can be used in templates
- Include JS and CSS that’s bundled by Vite
- Extend other Omni components
- Live in any view directory

## Defining Components

A standard component looks like this:

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

### Name, Path and Class

An Omni component's name, path and class map directly to one another and must all match. Additionally the namespace must include `Omni`. The part before `Omni` is the component prefix, and the part after is the component name, for example:

```
Name:  counter
Path:  resources/views/counter.blade.php
Class: App\Omni\Counter

Name:  my-package::events.stat-counter
Path:  vendor/my-package/resources/views/events/stat-counter.blade.php
Class: MyPackage\Omni\Events\StatCounter
```

### Lifecycle

Standard components support `mount` and `rendering` lifecycle hooks:

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

To pass additional variables to the template use the `with` method:

```php
protected function with()
{
    return [
        // ...
    ];
}
```

### Helpers

To create helper functions that you can call from the template define `protected` methods with the `#[Helper]` attribute:

```php
use JackSleight\LaravelOmni\Attributes\Helper;

#[Helper]
protected function random()
{
    // ...
}
```

```blade
<div>
    {{ $random() }}
</div>
```

### Attributes & Slots

Use attributes and slots as usual. Omni provides Livewire synthesizers to handle the serialization when necessary. 

```blade
<template render>
    <div {{ $attributes->class('p-4') }}>
        {{ $slot }}
    </div>
</template>
```

## Rendering Components

### Blade Templates

To render a component in a Blade template just use the usual syntax:

```blade
<x-counter :count="4" />
```

### Controllers

To render a component from a controller action use the view macro:

```php
return view()->mount('counter', ['count' => 4]);
```

Or the class directly:

```php
use App\Omni\Counter;

return (new Counter)(['count' => 4]);
```

### Routes

To mount a component to a route use the route macro:

```php
Route::mount('counter/{count}', 'counter');
```

Or the class directly:

```php
use App\Omni\Counter;

Route::get('counter/{count}', Counter::class);
```

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