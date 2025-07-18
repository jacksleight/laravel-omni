# Omni

Omni is a Laravel package and Vite plugin for building universal single-file Blade and Livewire components.

> [!WARNING]
> This is an experiment and could change. See known [differences](#known-differences) and [issues](#known-issues).

The core goals of Omni are:

- A single type of view, everything’s a component
- A single API for defining all components
- A single syntax for including all components
- A single directory structure for all components
- A single file for all component concerns (logic, template, bundled styles and scripts)

All Omni components can:

- Be standard Blade or Livewire components
- Be mounted to a route as a full-page component 
- Be rendered from a controller
- Be rendered in a template using `x-` syntax  
- Pull layouts into their templates
- Include styles and scripts that are bundled by Vite
- Extend other Omni components
- Use other Omni trait components
- Live in any view directory

This package will happily work alongside all normal views/components, it doesn't interfere with anything that's not an Omni component.

## Creating Components

You can create Omni components manually or using the `make:omni` command.

### Manual Creation

To create an Omni component manually, simply create a new view file anywhere in the views directory. They looks like this:

```blade
<?php 
namespace App\Omni;

class Counter
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

And to make it a Livewire component with a layout:

```blade
<?php 
namespace App\Omni;

class Counter
{
    public int $count = 0;

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

> [!CAUTION]
> Omni makes it trivial to switch a standard component to a Livewire component by simply updating the template tag. However when doing this you should carefully review all public properties as they will now be exposed client side.

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

### Using the Make Command

You can also create a new Omni component using the `make:omni` Artisan command:

```bash
php artisan make:omni
php artisan make:omni counter
php artisan make:omni counter --wire
```
### Lifecycle

Livewire components are handled by Livewire and run through the usual [lifecycle](https://livewire.laravel.com/docs/lifecycle-hooks), standard components support `mount` and `rendering` lifecycle hooks:

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

## Extending Components

You can extend components just like any other class, and include their templates using the `@omni` directive.

```blade
<?php 
namespace App\Omni\Button;

class Primary extends Button
{
    public $variant = 'primary';
} ?>

<template>
    @omni('#parent')
</template>
```

## Trait Components

You can define components as traits and include their templates using the `@omni` directive. This is useful if you need reusable component parts with the logic and template bundled together, or just want to break a large Livewire component up into more manageable chunks without actually mounting multiple seperate components.

```blade
<?php 
namespace App\Omni\User;

trait Contact
{
    public function saveContact()
    {
        // ...
    }
} ?>

<template omni:wire>
    <form>
        ...
        <button wire:click="saveContact">Save</button>
    </form>
</template>
```

```blade
<?php 
namespace App\Omni\User;

class Account
{
    use Contact;
    use Notifications;
    use Preferences;
} ?>

<template omni:wire>
    <div>
        @omni('#contact')
        @omni('#notifications')
        @omni('#preferences')
    </div>
</template>
```

## Rendering Components

### Blade Templates

To render a component in a Blade template use the `x-` syntax or `omni` directive:

```blade
<x-counter :count="4">
    Content
</x-counter>

@omni('counter', ['count' => 4])
```

### Controllers

To render a component from a controller action use the `omni` view macro or `mount` method:

```php
use App\Omni\Counter;
use JackSleight\LaravelOmni\Omni;

return view()->omni('counter', ['count' => 4]);
return view()->omni(Counter::class, ['count' => 4]);
return Omni::mount('counter', ['count' => 4]);
return Omni::mount(Counter::class, ['count' => 4]);
```

### Routes

To mount a component to a route use the `omni` route macro or class directly:

```php
use App\Omni\Counter;
use JackSleight\LaravelOmni\Omni;

Route::omni('counter/{count}', 'counter', ['count' => 4]);
Route::omni('counter/{count}', Counter::class, ['count' => 4]);
Route::get('counter/{count}', Counter::class);
```

## Component Modes

Omni components run in one of three modes depending on the `<template>` tag you declare and the template structure.

* **Standard Mode**  
  All components that declare a `omni` template run in standard mode. They support `mount` and `rendering` lifecycle hooks.

* **Livewire Mode**  
  Components that declare a `omni:wire` template and have no code outside of the `<template>` tag run in Livewire mode. They are handled by Livewire and through the usual [lifecycle](https://livewire.laravel.com/docs/lifecycle-hooks).

* **Combined Mode**  
  Components that declare a `omni:wire` template and have code outside of the `<template>` tag run in combined mode. Combined components are actually two instances of the same component. The part of the template outside the `<template>` tag runs in standard mode, and then the part of the template inside the `<template>` tag runs in Livewire mode.

## Component Detection

Any view that contains an Omni template tag or namespace declaration is considered an Omni component. If you don't need the class it can be omitted so long as you have a valid template tag. If you're building a standard component and dont need seperate style and script blocks you can omit the template tag so long as you have a valid namespace declaration.

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

* Individual attributes are not set as variables in the template scope.
* Conditionally rendering components by implementing `shouldRender` is not supported.

### Known Issues

* None?

### Unknown Differences & Issues

* Almost definitely.

## Troubleshooting

* **Error:** `Using $this when not in object context`  
  You may be trying to use a computed Livewire property in a standard mode render.
* **Error:** `Property [$...] not found on component`  
  You may be trying to use a computed Livewire property in a standard mode lifecycle hook.
  Use `$this->getId()` to check whether the component is running in Livewire mode (standard mode will have no ID).

## Credits

While Omni does not depend on [Livewire Volt](https://github.com/livewire/volt) and doesn't support any of it's functional syntax, it is obviously heavily inspired by Volt's single-file approach. This package would not exist if it wasn't for Volt, so a huge thanks to the Volt team. ❤️
