<?php

namespace JackSleight\LaravelOmni;

use Closure;
use Illuminate\Contracts\Routing\Registrar;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Contracts\View\View as ViewContract;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;
use Illuminate\View\Component as ViewComponent;
use JackSleight\LaravelOmni\Support\Utils;
use Livewire\Drawer\ImplicitRouteBinding;
use Livewire\Livewire;
use RuntimeException;

use function Livewire\invade;

class Manager
{
    const NAMESPACE_REGEX = '/namespace\s+[\w\\\]+\\\Omni(\\\[\w\\\]+)?\s*;/is';

    const CLASS_REGEX = '/^(\s*(<\?php.*?)\?>)/is';

    const DEFINITION_REGEX = '/class\s+([^\s]+)\s+\{/is';

    const TEMPLATE_REGEX = '/<template\s+(omni(?:\:wire)?)>(.*)<\/template>/is';

    const SCRIPT_REGEX = '/<style\s+omni>(.*?)<\/style>/is';

    const STYLE_REGEX = '/<script\s+omni>(.*?)<\/script>/is';

    const TEMPLATE_NONE = '<!-- __OMNI_TEMPLATE_NONE__ -->';

    protected array $cache = [];

    protected array $paths = [];

    public function path(string $path, ?string $prefix = null)
    {
        $this->paths[] = [
            'path' => $path,
            'prefix' => $prefix,
            'hash' => hash('xxh128', $prefix ?: $path),
        ];

        Blade::anonymousComponentPath($path, $prefix);
    }

    public function autoload(string $class): void
    {
        $info = $this->prepare(class: $class);
        if (! $info) {
            return;
        }

        require_once $info->classPath;
    }

    public function decompose(string $code): string
    {
        $omni = preg_match(static::TEMPLATE_REGEX, $code, $inner) || preg_match(static::NAMESPACE_REGEX, $code);
        if (! $omni) {
            return $code;
        }

        $path = Blade::getPath();
        $info = $this->identify(path: $path);
        if (! $info) {
            return $code;
        }

        if (! preg_match(static::CLASS_REGEX, $code, $class)) {
            $class = $this->makeClass($info);
        } else {
            $class = preg_replace(static::DEFINITION_REGEX, 'class $1 extends \\JackSleight\\LaravelOmni\\Component {', $class[2]);
        }

        $type = $inner[1] ?? 'omni';
        $inner = $inner[2] ?? '';
        $outer = preg_replace([
            static::CLASS_REGEX,
            static::SCRIPT_REGEX,
            static::STYLE_REGEX,
        ], '', $code);

        file_put_contents($info->classPath, trim($class));

        if ($type === 'omni:wire') {
            $empty = trim(preg_replace(static::TEMPLATE_REGEX, '', $outer)) === '';
            $outer = ! $empty
                ? preg_replace(static::TEMPLATE_REGEX, '@livewire("'.$info->name.'", get_defined_vars())', $outer)
                : static::TEMPLATE_NONE;
            file_put_contents($info->innerPath, $inner);
        } else {
            $outer = preg_replace(static::TEMPLATE_REGEX, $inner, $outer);
            if (file_exists($info->innerPath)) {
                unlink($info->innerPath);
            }
        }

        return $outer;
    }

    public function resolveStandard(string $class, array $attributes)
    {
        $info = $this->prepare(name: $attributes['view']);
        if (! $info) {
            return app()->make($class, $attributes);
        }

        $data = $attributes['data'];

        return $this->divert(fn ($more) => $this->mount($info->name, array_merge($data, $more)));
    }

    public function resolveLivewire(string $name): ?string
    {
        $info = $this->prepare(name: $name);
        if (! $info) {
            return null;
        }

        return $info->class;
    }

    public function mount(string $name, array $data = []): ViewContract|Htmlable|false
    {
        $info = $this->prepare(name: $name);
        if (! $info) {
            throw new RuntimeException("Component [{$name}] not found.");
        }

        $props = Utils::resolveProps($info->class, $data);

        if ($info->mode === Component::LIVEWIRE) {
            return new HtmlString(Livewire::mount($info->name, $props));
        }

        $component = app()->make($info->class);
        invade($component)->setMode(Component::STANDARD);
        $component->fill($props);

        Utils::callHooks($component, 'mount', $props);
        $view = $component->render();
        Utils::callHooks($component, 'rendering', ['view' => $view]);

        return $view;
    }

    public function route(string $uri, string $name, array $data = []): Route
    {
        return app(Registrar::class)
            ->get($uri, fn (Request $request) => $this->routeMount($request, $name, $data));
    }

    protected function routeMount(Request $request, string $name, array $data = [])
    {
        $info = $this->prepare(name: $name);
        if (! $info) {
            throw new RuntimeException("Component [{$name}] not found.");
        }

        $route = $request->route();
        try {
            $params = (new ImplicitRouteBinding(app()))
                ->resolveAllParameters($route, new ($info->class));
        } catch (ModelNotFoundException $exception) {
            if (method_exists($route, 'getMissing') && $route->getMissing()) {
                abort($route->getMissing()(request()));
            }
            throw $exception;
        }

        return $this->mount($name, [...$data, ...$params]);
    }

    public function identify(?string $name = null, ?string $path = null, ?string $class = null): object|false
    {
        if (! $name && ! $path && ! $class) {
            throw new \InvalidArgumentException('A name, path, or class is required.');
        }

        $key = $name ?? $path ?? $class;
        if (isset($this->cache[$key])) {
            return $this->cache[$key];
        }

        if ($name) {
            $name = $this->nameToName($name);
            $class = $this->nameToClass($name);
            $path = $this->nameToPath($name);
        } elseif ($path) {
            $name = $this->pathToName($path);
            $class = $this->nameToClass($name);
        } elseif ($class) {
            $name = $this->classToName($class);
            $path = $this->nameToPath($name);
        }

        if (! $name || ! $path || ! $class || ! file_exists($path)) {
            return false;
        }

        $outerPath = Blade::getCompiledPath($path);

        $info = (object) [
            'mode' => null,
            'name' => $name,
            'path' => $path,
            'class' => $class,
            'outerPath' => $outerPath,
            'innerPath' => Str::replaceEnd('.php', '.omni.blade.php', $outerPath),
            'classPath' => Str::replaceEnd('.php', '.omni.php', $outerPath),
        ];

        return $info;
    }

    public function prepare(?string $name = null, ?string $path = null, ?string $class = null): object|false
    {
        if (! $name && ! $path && ! $class) {
            throw new \InvalidArgumentException('A name, path, or is required.');
        }

        $key = $name ?? $path ?? $class;
        if (isset($this->cache[$key])) {
            return $this->cache[$key];
        }

        $info = $this->identify($name, $path, $class);
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
            $info->mode = Component::STANDARD;
        } elseif (Str::contains(file_get_contents($info->outerPath), static::TEMPLATE_NONE)) {
            $info->mode = Component::LIVEWIRE;
        } else {
            $info->mode = Component::COMBINED;
        }

        $this->cache[$name] = $info;
        $this->cache[$path] = $info;
        $this->cache[$class] = $info;

        return $info;
    }

    protected function nameToName($name): string|false
    {
        if (! $name) {
            return false;
        }

        [$prefix, $name] = Str::contains($name, '::')
            ? explode('::', $name)
            : [null, $name];

        $group = collect($this->paths)
            ->first(fn ($group) => $group['prefix'] === $prefix || $group['hash'] === $prefix);
        if (! $group) {
            return false;
        }

        return $group['hash'].'::'.$name;
    }

    protected function pathToName(string|false $path): string|false
    {
        if (! $path) {
            return false;
        }

        $group = collect($this->paths)
            ->first(fn ($group) => Str::startsWith($path, $group['path']));
        if (! $group) {
            return false;
        }

        $hash = $group['hash'];
        $name = Str::of($path)
            ->after($group['path'])
            ->before('.blade.php')
            ->trim('/')
            ->replace('/', '.')
            ->toString();

        return $hash.'::'.$name;
    }

    protected function nameToPath(string|false $name): string|false
    {
        if (! $name) {
            return false;
        }

        [$hash, $name] = explode('::', $name);
        $group = collect($this->paths)
            ->first(fn ($group) => $group['hash'] === $hash);
        if (! $group) {
            return false;
        }

        $base = $group['path'];
        $path = Str::of($name)
            ->replace('.', '/')
            ->toString();

        return $base.'/'.$path.'.blade.php';
    }

    protected function classToName(string|false $class): string|false
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
        $group = collect($this->paths)
            ->first(fn ($group) => $group['prefix'] === $prefix);
        if (! $group) {
            return false;
        }
        $hash = $group['hash'];

        $alias = implode('.', array_map(fn ($part) => Str::kebab($part), $parts));

        return $hash.'::'.$alias;
    }

    protected function nameToClass(string|false $name): string|false
    {
        if (! $name) {
            return false;
        }

        [$hash, $name] = explode('::', $name);
        $group = collect($this->paths)
            ->first(fn ($group) => $group['hash'] === $hash);
        if (! $group) {
            return false;
        }

        return collect(explode('.', $name))
            ->map(fn ($part) => Str::studly($part))
            ->prepend('Omni')
            ->prepend(Str::studly($group['prefix'] ?? 'App'))
            ->implode('\\');
    }

    protected function divert(Closure $callback)
    {
        return new class($callback) extends ViewComponent
        {
            public function __construct(protected Closure $callback) {}

            public function resolveView()
            {
                return fn ($data) => ($this->callback)(array_merge(
                    ['attributes' => $this->attributes],
                    Utils::resolveSlots($data),
                ));
            }

            public function data()
            {
                return [];
            }

            public function render() {}
        };
    }

    protected function makeClass($info)
    {
        $namespace = Str::beforeLast($info->class, '\\');
        $class = Str::afterLast($info->class, '\\');

        return <<<PHP
        <?php 
        namespace {$namespace};

        class {$class} extends \JackSleight\LaravelOmni\Component {}
        PHP;
    }
}
