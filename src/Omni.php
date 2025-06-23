<?php

namespace JackSleight\LaravelOmni;

use Closure;
use Illuminate\Contracts\Routing\Registrar;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Contracts\View\View as ViewContract;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;
use Illuminate\View\Component as ViewComponent;
use JackSleight\LaravelOmni\Support\Utils;
use Livewire\Livewire;

class Omni
{
    const CLASS_REGEX = '/^(\s*(<\?php.*?)\?>)/is';

    const TEMPLATE_REGEX = '/<template\s+(standard|livewire)>(.*)<\/template>/is';

    const SCRIPT_REGEX = '/<style\s+bundle>(.*?)<\/style>/is';

    const STYLE_REGEX = '/<script\s+bundle>(.*?)<\/script>/is';

    const TEMPLATE_EMPTY = '<?php /**OMNI_TEMPLATE_EMPTY**/ ?>';

    protected static array $cache = [];

    public static function autoload(string $class): void
    {
        $info = static::prepare(class: $class);
        if (! $info) {
            return;
        }

        require_once $info->classPath;
    }

    public static function decompose(string $code): string
    {
        if (! preg_match(static::CLASS_REGEX, $code, $class)) {
            return $code;
        }
        if (! preg_match(static::TEMPLATE_REGEX, $code, $inner)) {
            return $code;
        }

        $path = Blade::getPath();
        $info = static::identify(path: $path);
        if (! $info) {
            return $code;
        }

        $class = $class[2];
        $type = $inner[1];
        $inner = $inner[2];
        $outer = preg_replace([
            static::CLASS_REGEX,
            static::SCRIPT_REGEX,
            static::STYLE_REGEX,
        ], '', $code);

        file_put_contents($info->classPath, trim($class));

        if ($type === Component::LIVEWIRE) {
            $empty = trim(preg_replace(static::TEMPLATE_REGEX, '', $outer)) === '';
            $outer = ! $empty
                ? preg_replace(static::TEMPLATE_REGEX, '@livewire("'.$info->name.'", get_defined_vars())', $outer)
                : static::TEMPLATE_EMPTY;
            file_put_contents($info->innerPath, $inner);
        } else {
            $outer = preg_replace(static::TEMPLATE_REGEX, $inner, $outer);
            if (file_exists($info->innerPath)) {
                unlink($info->innerPath);
            }
        }

        return $outer;
    }

    public static function resolveStandard(string $class, array $attributes)
    {
        $info = static::prepare(name: $attributes['view']);
        if (! $info) {
            return app()->make($class, $attributes);
        }

        $data = $attributes['data'];

        return static::divert(fn ($slots) => static::mount($info->name, array_merge($data, $slots)));
    }

    public static function resolveLivewire(string $name): ?string
    {
        $info = static::prepare(name: $name);
        if (! $info) {
            return null;
        }

        return $info->class;
    }

    public static function mount(string $name, array $data = []): ViewContract|Htmlable|null
    {
        $info = static::prepare(name: $name);
        if (! $info) {
            return null;
        }

        $props = Utils::resolveProps($info->class, $data);

        if ($info->type === Component::LIVEWIRE) {
            return new HtmlString(Livewire::mount($info->name, $props));
        }

        $component = app()->make($info->class);
        $component->fill($props);

        Utils::callHooks($component, 'mount', $props);
        $view = $component->render(true);
        Utils::callHooks($component, 'rendering', ['view' => $view]);

        return $view;
    }

    public static function route(string $uri, string $name, array $data = []): Route
    {
        return app(Registrar::class)
            ->get($uri, fn (Request $request) => static::mount($name, [
                ...$data,
                ...$request->route()->parameters(),
            ]));
    }

    public static function identify(?string $name = null, ?string $path = null, ?string $class = null): object|false
    {
        if (! $name && ! $path && ! $class) {
            throw new \InvalidArgumentException('A name, path, or class is required.');
        }

        $key = $name ?? $path ?? $class;
        if (isset(static::$cache[$key])) {
            return static::$cache[$key];
        }

        if ($name) {
            $name = static::nameToName($name);
            $class = static::nameToClass($name);
            $path = static::nameToPath($name);
        } elseif ($path) {
            $name = static::pathToName($path);
            $class = static::nameToClass($name);
        } elseif ($class) {
            $name = static::classToName($class);
            $path = static::nameToPath($name);
        }

        if (! $name || ! $path || ! $class) {
            static::$cache[$name] = false;
            static::$cache[$path] = false;
            static::$cache[$class] = false;

            return false;
        }

        $outerPath = Blade::getCompiledPath($path);

        $info = (object) [
            'type' => null,
            'name' => $name,
            'path' => $path,
            'class' => $class,
            'outerPath' => $outerPath,
            'innerPath' => Str::replaceEnd('.php', '.omni.blade.php', $outerPath),
            'classPath' => Str::replaceEnd('.php', '.omni.php', $outerPath),
        ];

        static::$cache[$name] = $info;
        static::$cache[$path] = $info;
        static::$cache[$class] = $info;

        return $info;
    }

    public static function prepare(?string $name = null, ?string $path = null, ?string $class = null): object|false
    {
        if (! $name && ! $path && ! $class) {
            throw new \InvalidArgumentException('A name, path, or is required.');
        }

        $key = $name ?? $path ?? $class;
        if (isset(static::$cache[$key])) {
            return static::$cache[$key];
        }

        $info = static::identify($name, $path, $class);
        if (! $info) {
            return false;
        }

        if (! file_exists($info->outerPath) || filemtime($info->path) > filemtime($info->outerPath)) {
            Blade::compile($info->path);
        }

        if (! file_exists($info->classPath)) {
            return false;
        }

        if (! file_exists($info->innerPath)) {
            $info->type = Component::STANDARD;
        } elseif (Str::contains(file_get_contents($info->outerPath), static::TEMPLATE_EMPTY)) {
            $info->type = Component::LIVEWIRE;
        } else {
            $info->type = Component::COMBINED;
        }

        return $info;
    }

    protected static function nameToName($name): string|false
    {
        if (! $name) {
            return false;
        }

        [$prefix, $name] = Str::contains($name, '::')
            ? explode('::', $name)
            : [null, $name];

        $group = collect(app('blade.compiler')->getAnonymousComponentPaths())
            ->first(fn ($group) => $group['prefix'] === $prefix || $group['prefixHash'] === $prefix);
        if (! $group) {
            return false;
        }

        return $group['prefixHash'].'::'.$name;
    }

    protected static function pathToName(string|false $path): string|false
    {
        if (! $path) {
            return false;
        }

        $group = collect(app('blade.compiler')->getAnonymousComponentPaths())
            ->first(fn ($group) => Str::startsWith($path, $group['path']));
        if (! $group) {
            return false;
        }

        $hash = $group['prefixHash'];
        $name = Str::of($path)
            ->after($group['path'])
            ->before('.blade.php')
            ->trim('/')
            ->replace('/', '.')
            ->before('.index')
            ->toString();

        return $hash.'::'.$name;
    }

    protected static function nameToPath(string|false $name): string|false
    {
        if (! $name) {
            return false;
        }

        [$hash, $name] = explode('::', $name);
        $group = collect(app('blade.compiler')->getAnonymousComponentPaths())
            ->first(fn ($group) => $group['prefixHash'] === $hash);
        if (! $group) {
            return false;
        }

        $base = $group['path'];
        $path = Str::of($name)
            ->before('.index')
            ->replace('.', '/')
            ->toString();

        return $base.'/'.$path.'.blade.php';
    }

    protected static function classToName(string|false $class): string|false
    {
        if (! $class) {
            return false;
        }

        if (! Str::contains($class, '\\Omni\\')) {
            return false;
        }

        $parts = explode('\\', $class);

        unset($parts[1]);

        $prefix = Str::kebab(array_shift($parts));
        if ($prefix === 'app') {
            $prefix = null;
        }
        $group = collect(app('blade.compiler')->getAnonymousComponentPaths())
            ->first(fn ($group) => $group['prefix'] === $prefix);
        if (! $group) {
            return false;
        }
        $hash = $group['prefixHash'];

        $alias = implode('.', array_map(fn ($part) => Str::kebab($part), $parts));

        return $hash.'::'.$alias;
    }

    protected static function nameToClass(string|false $name): string|false
    {
        if (! $name) {
            return false;
        }

        [$hash, $name] = explode('::', $name);
        $group = collect(app('blade.compiler')->getAnonymousComponentPaths())
            ->first(fn ($group) => $group['prefixHash'] === $hash);
        if (! $group) {
            return false;
        }

        return collect(explode('.', $name))
            ->map(fn ($part) => Str::studly($part))
            ->prepend('Omni')
            ->prepend(Str::studly($group['prefix'] ?? 'App'))
            ->implode('\\');
    }

    protected static function divert(Closure $callback)
    {
        return new class($callback) extends ViewComponent
        {
            public function __construct(protected Closure $callback) {}

            public function resolveView()
            {
                return fn ($data) => ($this->callback)(Utils::resolveSlots($data));
            }

            public function data()
            {
                return [];
            }

            public function render() {}
        };
    }
}
