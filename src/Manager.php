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
use JackSleight\LaravelOmni\Support\Syntax;
use JackSleight\LaravelOmni\Support\Utils;
use Livewire\Drawer\ImplicitRouteBinding;
use Livewire\Livewire;
use RuntimeException;

class Manager
{
    const USE_REGEX = '/(?<!@)@use(\s*\(((?>[^()]+)|(?1))*\))/is';

    const CLASS_REGEX = '/(?<!@)@omni(?:(\s*\(((?>[^()]+)|(?1))*\))|\s|$)/is';

    const WIRE_REGEX = '/@wire(.*)@endwire/is';

    const SCRIPT_REGEX = '/<style\s+omni>(.*?)<\/style>/is';

    const STYLE_REGEX = '/<script\s+omni>(.*?)<\/script>/is';

    const TEMPLATE_NONE = '<!-- __OMNI_TEMPLATE_NONE__ -->';

    protected array $cache = [];

    protected array $paths = [];

    public function addPath(string $path, ?string $prefix = null)
    {
        $this->paths[] = [
            'path' => rtrim($path, '/').'/',
            'prefix' => $prefix,
            'hash' => hash('xxh128', $prefix ?: $path),
        ];

        Blade::anonymousComponentPath($path, $prefix);
    }

    public function getPaths()
    {
        return $this->paths;
    }

    public function autoload(string $class): void
    {
        $info = $this->lookup(class: $class);
        if (! $info) {
            return;
        }

        require_once $info->classPath;
    }

    public function decompose(string $code): string
    {
        $omni = preg_match(static::CLASS_REGEX, $code, $class);
        if (! $omni) {
            return $code;
        }

        $path = Blade::getPath();
        if (! $path) {
            return $code;
        }

        $info = $this->define(path: $path);
        if (! $info) {
            return $code;
        }

        $wire = preg_match(static::WIRE_REGEX, $code, $inner);
        $inner = $inner[1] ?? '';
        $outer = preg_replace([
            static::CLASS_REGEX,
            static::SCRIPT_REGEX,
            static::STYLE_REGEX,
        ], '', $code);

        preg_match_all(static::USE_REGEX, $code, $uses);
        $uses = collect($uses[1])
            ->map(fn ($use) => substr($use, 1, -1))
            ->filter()
            ->all();

        $class = Syntax::generateClass($info, substr($class[1] ?? '', 1, -1), $uses);
        file_put_contents($info->classPath, "<?php\n\n".trim($class));

        if ($wire) {
            $empty = trim(preg_replace([
                static::WIRE_REGEX,
                static::USE_REGEX,
            ], '', $outer)) === '';
            $outer = ! $empty
                ? preg_replace(static::WIRE_REGEX, '{!! Livewire\Livewire::mount("'.$info->name.'", get_defined_vars()) !!}', $outer)
                : static::TEMPLATE_NONE;
            $inner = Blade::compileString($inner);
            file_put_contents($info->innerPath, $inner);
        } else {
            $outer = preg_replace(static::WIRE_REGEX, $inner, $outer);
            if (file_exists($info->innerPath)) {
                unlink($info->innerPath);
            }
        }

        return $outer;
    }

    public function resolveStandard(string $class, array $attributes)
    {
        if (! isset($attributes['view'])) {
            return app()->make($class, $attributes);
        }

        $info = $this->lookup(name: $attributes['view']);
        if (! $info) {
            return app()->make($class, $attributes);
        }

        $data = $attributes['data'] ?? [];

        return $this->divert(fn ($more) => $this->mount($info->name, array_merge($data, $more)));
    }

    public function resolveLivewire(string $name): ?string
    {
        $info = $this->lookup(name: $name);
        if (! $info) {
            return null;
        }

        return $info->class;
    }

    public function mount(string $name, array $data = []): ViewContract|Htmlable
    {
        $info = $this->lookup(name: $name);
        if (! $info || ! class_exists($info->class)) {
            throw new RuntimeException("Component [{$name}] not found.");
        }
        if (! is_a($info->class, Component::class, true)) {
            throw new RuntimeException("Component [{$name}] does not extend the Omni Component class.");
        }

        $props = Utils::resolveProps($info->class, $info->mode, $data);
        $mount = array_merge($data, $props);

        if (array_key_exists('when', $props) && ! $props['when']) {
            return new HtmlString('');
        }

        if ($info->mode === Component::LIVEWIRE) {
            return new HtmlString(Livewire::mount($info->name, $mount));
        }

        $component = app()->make($info->class);
        $component->fill($props);

        Utils::callHooks($component, 'mount', $mount);
        $view = $component->render();
        Utils::callHooks($component, 'rendering', ['view' => $view]);

        return $view;
    }

    public function directive($expression): string
    {
        return <<<PHP
        <?php echo JackSleight\LaravelOmni\Omni::include({$expression}, array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1])); ?>
        PHP;
    }

    public function include(string $name, array $data = []): ViewContract|Htmlable
    {
        if (! Str::startsWith($name, '#')) {
            return $this->mount($name, $data);
        }

        $name = Str::after($name, '#');

        $component = $data['__omni'] ?? null;
        if (! $component || ! is_a($component, Component::class)) {
            throw new RuntimeException("Cannot include partial [{$name}] in non-component context");
        }

        $class = $name === 'parent'
            ? get_parent_class($component::class)
            : (Utils::getTraitNames($component::class)[$name] ?? null);
        if (! $class) {
            throw new RuntimeException("Component [{$name}] not found on component [".$component::class.'].');
        }

        $info = $this->lookup(class: $class);
        if (! $info) {
            throw new RuntimeException("Component [{$name}] not found for component [{".$component::class.'}].');
        }

        if ($info->mode === Component::LIVEWIRE) {
            return view()->file($info->innerPath, $data);
        }

        return view()->file($info->outerPath, $data);
    }

    public function route(string $uri, string $name, array $data = []): Route
    {
        return app(Registrar::class)
            ->get($uri, fn (Request $request) => $this->request($request, $name, $data));
    }

    public function request(Request $request, string $name, array $data = [])
    {
        $info = $this->lookup(name: $name);
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

    public function define(?string $name = null, ?string $path = null, ?string $class = null): object|false
    {
        if (! $name && ! $path && ! $class) {
            throw new \InvalidArgumentException('A name, path, or class is required.');
        }

        if ($name) {
            $name = $this->nameToName($name);
            $path = $this->nameToPath($name);
            $name = $this->pathToName($path);
            $class = $this->nameToClass($name);
        } elseif ($path) {
            $name = $this->pathToName($path);
            $class = $this->nameToClass($name);
        } elseif ($class) {
            $path = $this->classToPath($class);
            $name = $this->pathToName($path);
        }

        if (! $name || ! $path || ! $class) {
            return false;
        }

        $outerPath = Blade::getCompiledPath($path);

        $info = (object) [
            'mode' => null,
            'name' => $name,
            'path' => $path,
            'class' => $class,
            'outerPath' => $outerPath,
            'innerPath' => Str::replaceEnd('.php', '.omni.inner.php', $outerPath),
            'classPath' => Str::replaceEnd('.php', '.omni.class.php', $outerPath),
        ];

        return $info;
    }

    public function prepare(?string $name = null, ?string $path = null, ?string $class = null): object|false
    {
        if (! $name && ! $path && ! $class) {
            throw new \InvalidArgumentException('A name, path, or class is required.');
        }

        $info = $this->define($name, $path, $class);
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

        return $info;
    }

    public function lookup(?string $name = null, ?string $path = null, ?string $class = null): object|false
    {
        if (! $name && ! $path && ! $class) {
            throw new \InvalidArgumentException('A name, path, or class is required.');
        }

        $key = $name ?? $path ?? $class;
        if (isset($this->cache[$key])) {
            return $this->cache[$key];
        }

        $info = $this->prepare($name, $path, $class);

        $this->cache[$name] = $info;
        $this->cache[$path] = $info;
        $this->cache[$class] = $info;

        return $info;
    }

    public function exists(?string $name = null, ?string $path = null, ?string $class = null): object|false
    {
        if (! $name && ! $path && ! $class) {
            throw new \InvalidArgumentException('A name, path, or class is required.');
        }

        return $this->lookup($name, $path, $class) !== false;
    }

    protected function nameToName($name): string|false
    {
        if (! $name) {
            return false;
        }

        [$prefix, $name] = Str::contains($name, '::')
            ? explode('::', $name)
            : [null, $name];

        $set = collect($this->paths)
            ->first(fn ($set) => $set['prefix'] === $prefix || $set['hash'] === $prefix);
        if (! $set) {
            return false;
        }

        return $set['hash'].'::'.$name;
    }

    protected function pathToName(string|false $path): string|false
    {
        if (! $path) {
            return false;
        }

        $set = collect($this->paths)
            ->first(fn ($set) => Str::startsWith($path, $set['path']));
        if (! $set) {
            return false;
        }

        $hash = $set['hash'];
        $name = Str::of($path)
            ->after($set['path'])
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
        $name = Str::replace('.', '/', $name);
        $path = collect($this->paths)
            ->where(fn ($set) => $set['hash'] === $hash)
            ->flatMap(fn ($set) => [
                $set['path'].$name.'/'.Str::afterLast($name, '/').'.blade.php',
                $set['path'].$name.'.blade.php',
            ])
            ->first(fn ($path) => file_exists($path));
        if (! $path) {
            return false;
        }

        return $path;
    }

    protected function classToPath(string|false $class): string|false
    {
        if (! $class || ! Str::contains($class, '\\Omni\\')) {
            return false;
        }

        $prefix = Str::of($class)
            ->before('\\Omni\\')
            ->kebab()
            ->toString();
        if ($prefix === 'app') {
            $prefix = null;
        }

        $name = Str::of($class)
            ->after('\\Omni\\')
            ->explode('\\')
            ->map(fn ($part) => Str::kebab($part))
            ->implode('/');

        $path = collect($this->paths)
            ->map(fn ($set) => $set['path'].$name.'.blade.php')
            ->first(fn ($path) => file_exists($path));
        if (! $path) {
            return false;
        }

        return $path;
    }

    protected function nameToClass(string|false $name): string|false
    {
        if (! $name) {
            return false;
        }

        [$hash, $name] = explode('::', $name);
        $set = collect($this->paths)
            ->first(fn ($set) => $set['hash'] === $hash);
        if (! $set) {
            return false;
        }

        return collect(explode('.', $name))
            ->map(fn ($part) => Str::studly($part))
            ->prepend('Omni')
            ->prepend(Str::studly($set['prefix'] ?? 'App'))
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
