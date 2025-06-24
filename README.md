# Omni

Omni is a Laravel package and Vite plugin for building universal single-file Blade components, with or without Livewire features.

> **⚠️ Experimental:** This package is experimental and could change. Some things may not behave as expected, see known [differences](#known-differences) and [issues](#known-issues).

The core goals of Omni are:

- A single API for defining components
- A single syntax for mounting and rendering components
- A single directory structure for organising components
- A single file for all component concerns (logic, template, styles and scripts)

All Omni components can:

- Opt in or out of Livewire features
- Be mounted to a route as a full-page component 
- Be rendered from a controller
- Be rendered in a template using `x-` syntax  
- Use layouts, slots, and attribute bags
- Define template helper functions
- Include JS and CSS that’s bundled by Vite
- Extend other Omni components
- Live in any view directory

## Creating Components

To create an Omni component simply create a new view file anywhere in the views directory, they do not need to live in `/components` or `/livewire`.

An Omni component looks like this:

```blade
<?php 
namespace App\Omni;

use JackSleight\LaravelOmni\Component;

class Counter extends Component
{
    public int $count = 0;
} ?>

<template omni>
    <div>
        {{ $count }}
    </div>
</template>

<style omni>
    /* ... */
</style>

<script omni>
    /* ... */
</script>
```

And if you want Livewire features and a layout:

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

<x-layout>
    <template omni:wire> {{-- Make it Livewire! --}}
        <div>
            {{ $count }}
            <button wire:click="increment">+</button>
        </div>
    </template>
</x-layout>
```

### Name, Path and Class

An Omni component's name, path and class map directly to one another and must all match. The class namespace must include `Omni`. The part before `Omni` is the component prefix, and the part after is the component name, for example:

```
Name:  counter
Path:  resources/views/counter.blade.php
Class: App\Omni\Counter

Name:  shop-app::products.list
Path:  vendor/shop-app/resources/views/products/list.blade.php
Class: ShopApp\Omni\Products\List
```

A blank name prefix maps to the `App` class namespace.

### Lifecycle

Livewire components run through the usual [Livewire lifecycle](https://livewire.laravel.com/docs/lifecycle-hooks), non-Livewire components support the `mount` and `rendering` lifecycle hooks:

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

Use attributes and slots as usual. If you're using them in Livewire components Omni provides synthesizers to handle the serialization. 

```blade
<template omni>
    <div {{ $attributes->class('p-4') }}>
        {{ $slot }}
    </div>
</template>
```

## Rendering Components

### Blade Templates

To render any component in a Blade template use the `x-` syntax:

```blade
<x-counter :count="4">
    Content
</x-counter>
```

### Controllers

To render any component from a controller action use the `omni` view macro or `mount` method:

```php
use App\Omni\Counter;
use JackSleight\LaravelOmni\Omni;

return view()->omni('counter', ['count' => 4]);
return view()->omni(Counter::class, ['count' => 4]);
return Omni::mount('counter', ['count' => 4]);
return Omni::mount(Counter::class, ['count' => 4]);
```

### Routes

To mount any component to a route use the `omni` route macro or class directly:

```php
use App\Omni\Counter;
use JackSleight\LaravelOmni\Omni;

Route::omni('counter/{count}', 'counter', ['count' => 4]);
Route::omni('counter/{count}', Counter::class, ['count' => 4]);
Route::get('counter/{count}', Counter::class);
```

### Component Execution

Omni components will execute in one of three modes depending on the `<template>` tag you declare and the template structure.

* **Standard Mode**  
  All components that declare a `omni` template execute in standard mode. They support `mount` and `rendering` lifecycle hooks.

* **Livewire Mode**  
  Components that declare a `omni:wire` template and have no code outside of the `<template>` tag execute in Livewire mode. They run through the usual [Livewire lifecycle](https://livewire.laravel.com/docs/lifecycle-hooks).

* **Combined Mode**  
  Components that declare a `omni:wire` template and have code outside of the `<template>` tag execute in combined mode. Combined components are actually two instances of the same component. The part of the template outside the `<template>` tag is executed in standard mode, and then the part of the template inside the `<template>` tag is executed in Livewire mode.

## Bundling Scripts & Styles

...

## Differences & Issues

### Known Differences

These are intentional differences in the way Omni components behave compared to normal Blade or Livewire components.

* Template [helper functions](#helpers) are defined as protected methods with an attribute, not public methods. This is because public methods are reserved for Livewire actions.
* Attributes are not exposed as variables in the template scope, they only exist in the attribute bag. This is to keep things tidy and avoid kebab-case variable names.

### Known Issues

* Route model binding is not yet supported.

### Unknown Differences & Issues

* Almost definitely.

## Credits

While Omni does not depend on [Livewire Volt](https://livewire.laravel.com/docs/volt) and doesn't support any of it's functional syntax, it is obviously heavily inspired by Volt's single-file structure. This package would not exist if it wasn't for Volt, so a huge thanks to the Volt team. ❤️