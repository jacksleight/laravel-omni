# Omni

Omni is a Laravel package and Vite plugin for building universal Blade and Livewire components.

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
@omni(class
{
    public int $count = 0;
})

<div>
    {{ $count }}
</div>

<style bundle>
    /* ... */
</style>

<script bundle>
    /* ... */
</script>
```

And to make it a Livewire component with a layout:

```blade
@omni(class
{
    public int $count = 0;

    public function increment()
    {
        $this->count++;
    }
})

<x-layout>
    @wire
        <div>
            {{ $count }}
            <button wire:click="increment">+</button>
        </div>
    @endwire
</x-layout>
```

Omni also supports array syntax just like the `@props` directive:

```blade
@omni([
    'count' => 0,
])

<div>
    {{ $count }}
</div>
```

> [!CAUTION]
> Omni makes it trivial to switch a standard component to a Livewire component by simply updating the template tag. However when doing this you should carefully review all public properties as they will now be exposed client side.

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

You can extend components just like any other class, and include their templates using the `@mount` directive.

```blade
@omni(class extends Button
{
    public $variant = 'primary';
})

@mount('#parent')
```

## Trait Components

You can define components as traits and include their templates using the `@omni` directive. This is useful if you need reusable component parts with the logic and template bundled together, or just want to break a large Livewire component up into more manageable chunks without actually mounting multiple seperate components.

```blade
@omni(trait
{
    public function saveContact()
    {
        // ...
    }
})

@wire
    <form>
        ...
        <button wire:click="saveContact">Save</button>
    </form>
@endwire
```

```blade
@omni(class
{
    use Contact;
    use Notifications;
    use Preferences;
})

@wire
    <div>
        @mount('#contact')
        @mount('#notifications')
        @mount('#preferences')
    </div>
@endwire
```

## Rendering Components

### Blade Templates

To render a component in a Blade template use the `x-` syntax or `@mount` directive:

```blade
<x-counter :count="4">
    Content
</x-counter>

@mount('counter', ['count' => 4])
```

### Controllers

To render a component from a controller action use the `mount` view macro or `mount` method:

```php
use App\Omni\Counter;
use JackSleight\LaravelOmni\Omni;

return view()->mount('counter', ['count' => 4]);
return view()->mount(Counter::class, ['count' => 4]);
return Omni::mount('counter', ['count' => 4]);
return Omni::mount(Counter::class, ['count' => 4]);
```

### Routes

To mount a component to a route use the `mount` route macro or class directly:

```php
use App\Omni\Counter;
use JackSleight\LaravelOmni\Omni;

Route::mount('counter/{count}', 'counter', ['count' => 4]);
Route::mount('counter/{count}', Counter::class, ['count' => 4]);
Route::get('counter/{count}', Counter::class);
```

## Component Modes

Omni components run in one of three modes depending on the template structure.

* **Standard Mode**  
  Components that dont use the `@wire` directive run in standard mode. They support `mount` and `rendering` lifecycle hooks.

* **Livewire Mode**  
  Components that use the `@wire` directive and have no code outside of it run in Livewire mode. They are handled by Livewire and through the usual [lifecycle](https://livewire.laravel.com/docs/lifecycle-hooks).

* **Combined Mode**  
  Components that use the `@wire` directive and have code outside of it run in combined mode. Combined components are actually two instances of the same component. The part of the template outside the `@wire` directive runs in standard mode, and then the part of the template inside the `@wire` directive runs in Livewire mode.

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
