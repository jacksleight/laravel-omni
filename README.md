# Omni

Omni is a Laravel package and Vite plugin for building universal single-file Blade and Livewire components.

The core goals of Omni are:

- A single type of view, everything’s a component
- A single API for defining all components
- A single syntax for mounting and rendering all components
- A single directory structure for organising all components
- A single file for all component concerns (logic, template, styles and scripts)

All Omni components can:

- Be standard Blade or Livewire components
- Be mounted to a route as a full-page component 
- Be rendered from a controller
- Be rendered in a template using `x-` syntax  
- Pull layouts into their templates
- Use slots and attribute bags
- Define template helper functions
- Include JS and CSS that’s bundled by Vite
- Extend other Omni components
- Live in any view directory

> **⚠️ Experimental:** This package is experimental and could change. Some things may not behave as expected, see known [differences](#known-differences) and [issues](#known-issues).

## Creating Components

To create an Omni component simply create a new view file anywhere in the views directory. They looks like this:

```blade
<?php 
namespace App\Omni;

use JackSleight\LaravelOmni\Component;

class Counter extends Component
{
    protected int $count = 0;
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

And to make it a Livewire component with a layout:

```blade
<?php 
namespace App\Omni;

use JackSleight\LaravelOmni\Component;

class Counter extends Component
{
    public int $count = 0; /* Make this public */

    public function increment()
    {
        $this->count++;
    }
} ?>

<x-layout>
    <template omni:wire> {{-- Enable Livewire --}}
        <div>
            {{ $count }}
            <button wire:click="increment">+</button>
        </div>
    </template>
</x-layout>
```

### Name, Path and Class

An Omni component's name, path and class must all match. The class namespace must include `Omni`. The part before `Omni` is the component prefix, and the part after is the component name, for example:

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

Livewire components run through the usual [Livewire lifecycle](https://livewire.laravel.com/docs/lifecycle-hooks), standard components support the `mount` and `rendering` lifecycle hooks:

```php
public function mount($value)
{
    // ...
}

public function rendering($view)
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

### Property Visibility

Public properties have special meaning in Livewire components, so Omni needs a way to define properties for standard components without the risk of them being exposed by mistake when switching a standard component to Livewire. Therefore it's best practive to define standard component properties as protected instead of public, and only switch them to public when you need to. Protected properties are avalibale in the standard component template scope.

### Component Execution

Omni components will execute in one of three modes depending on the `<template>` tag you declare and the template structure.

* **Standard Mode**  
  All components that declare a `omni` template execute in standard mode. They support `mount` and `rendering` lifecycle hooks. All public and protected properties are avalibale in the template scope. All protected methods are avalibale in the template scope.

* **Livewire Mode**  
  Components that declare a `omni:wire` template and have no code outside of the `<template>` tag execute in Livewire mode. They run through the usual [Livewire lifecycle](https://livewire.laravel.com/docs/lifecycle-hooks). All public properties are avalibale in the template scope. All protected methods are avalibale in the template scope.

* **Combined Mode**  
  Components that declare a `omni:wire` template and have code outside of the `<template>` tag execute in combined mode. Combined components are actually two instances of the same component. The part of the template outside the `<template>` tag is executed in standard mode, and then the part of the template inside the `<template>` tag is executed in Livewire mode.

## Bundling Scripts & Styles

Any Omni `<script>` and `<style>` blocks will be excluded from the templates and can instead be included in your JS and CSS bundles using the provided Vite plugin. To set that up add the Omni package as a dependency in `package.json`:

```js
{
    "dependencies": {
        "omni": "file:./vendor/jacksleight/laravel-omni"
    }
}
```

Then add the plugin in `vite.config.js`:

```js
import omni from 'omni/plugins/vite';

export default defineConfig({
    plugins: [
        omni({ views: [
            __dirname + '/resources/views',
        ] }),
    ],
});
```

And finally import the Omni scripts and styles into your `app.js` and `app.css` files:

```js
import 'omni/scripts';
```

```css
@import 'omni/styles';
```

## Differences & Issues

### Known Differences

These are intentional differences in the way Omni components behave compared to normal Blade or Livewire components.

* Standard component protected properties can be filled from tag attributes or parameter arrays.
* Standard component protected properties are available in the template scope. They are not exposed on the client side.
* Protected methods are available in the template scope. They are not exposed on the client side.
* Attributes are not exposed as variables in the template scope.
* Conditionally rendering components by implementing `shouldRender` is not supported.

### Known Issues

* Route model binding is not yet supported.

### Unknown Differences & Issues

* Almost definitely.

## Troubleshooting

* **Error:** `Using $this when not in object context`  
  You may be trying to use a computed Livewire property in a standard non-Livewire template.

## Credits

While Omni does not depend on [Livewire Volt](https://github.com/livewire/volt) and doesn't support any of it's functional syntax, it is obviously heavily inspired by Volt's single-file approach. This package would not exist if it wasn't for Volt, so a huge thanks to the Volt team. ❤️