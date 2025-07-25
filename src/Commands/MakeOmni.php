<?php

namespace JackSleight\LaravelOmni\Commands;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Str;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\text;

class MakeOmni extends Command
{
    protected $signature = 'make:omni {name?} {--wire=false}';

    protected $description = 'Create a new Omni component';

    protected Filesystem $files;

    public function __construct(Filesystem $files)
    {
        parent::__construct();
        $this->files = $files;
    }

    public function handle()
    {
        $name = $this->argument('name') ?: text(
            label: 'What should the component be named?',
            placeholder: 'counter',
            required: true,
            validate: fn (string $value) => strlen($value) < 1 ? 'The component name is required.' : null
        );

        $name = $this->normalizeName($name);

        $wire = $this->option('wire') ?: confirm(
            label: 'Should this be a Livewire component?',
            default: false
        );

        $class = $this->getClass($name);
        $namespace = $this->getNamespace($name);
        $path = $this->getPath($name);

        if ($this->files->exists($path)) {
            $this->error("Component [{$name}] already exists!");

            return false;
        }

        $this->makeDirectory($path);

        $stub = $wire ? $this->getLivewireStub() : $this->getStandardStub();

        $this->files->put($path, $stub);

        $this->info("Component [{$name}] created successfully.");

        return true;
    }

    protected function normalizeName(string $name): string
    {
        $name = str_replace(['/', '\\'], '.', $name);

        $parts = explode('.', $name);

        return collect($parts)
            ->map(fn (string $part) => Str::kebab($part))
            ->filter()
            ->implode('.');
    }

    protected function getClass(string $name): string
    {
        return Str::studly(Str::afterLast($name, '.'));
    }

    protected function getNamespace(string $name): string
    {
        $parts = explode('.', $name);
        array_pop($parts);

        $namespace = 'App\\Omni';
        if (! empty($parts)) {
            $namespace .= '\\'.implode('\\', array_map([Str::class, 'studly'], $parts));
        }

        return $namespace;
    }

    protected function getPath(string $name): string
    {
        $path = str_replace('.', '/', $name);

        return resource_path("views/{$path}.blade.php");
    }

    protected function makeDirectory(string $path): void
    {
        $directory = dirname($path);

        if (! $this->files->isDirectory($directory)) {
            $this->files->makeDirectory($directory, 0755, true);
        }
    }

    protected function getStandardStub(): string
    {
        return '@omni(class
{
    protected string $message = \'Hello from {{ class }}!\';
})

<div>
    {{ $message }}
</div>

<style bundle>
    /* Component styles */
</style>

<script bundle>
    /* Component scripts */
</script>
';
    }

    protected function getLivewireStub(): string
    {
        return '@omni(class
{
    public string $message = \'Hello from {{ class }}!\';

    public function updateMessage()
    {
        $this->message = \'Updated: \' . now()->format(\'H:i:s\');
    }
})

@wire
    <div>
        <p>{{ $message }}</p>
        <button wire:click="updateMessage">Update</button>
    </div>
@endwire

<style bundle>
    /* Component styles */
</style>

<script bundle>
    /* Component scripts */
</script>
';
    }
}
