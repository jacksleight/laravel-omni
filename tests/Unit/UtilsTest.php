<?php

use Illuminate\View\ComponentAttributeBag;
use JackSleight\LaravelOmni\Component;
use JackSleight\LaravelOmni\Support\Utils;

beforeEach(function () {
    $this->testClass = new class extends Component {
        public string $name = '';
        public int $count = 0;
        public bool $active = true;

        public function mount(string $name): void
        {
            $this->name = $name;
        }

        public function rendering(): void
        {
            // Test hook method
        }
    };
});

it('gets property names from a class', function () {
    $properties = Utils::getPropertyNames($this->testClass::class);

    expect($properties)
        ->toBeArray()
        ->toContain('name', 'count', 'active', 'attrs', 'slot')
        ->not->toContain('id'); // Should exclude parent properties
});

it('gets method argument names', function () {
    $arguments = Utils::getMethodArgumentNames($this->testClass::class, 'mount');

    expect($arguments)
        ->toBeArray()
        ->toBe(['name']);
});

it('resolves props from data', function () {
    $data = [
        'name' => 'test',
        'count' => 5,
        'extra' => 'ignored',
        'attributes' => new ComponentAttributeBag(['class' => 'btn', 'name' => 'overridden']),
    ];

    $props = Utils::resolveProps($this->testClass::class, Component::STANDARD, $data);

    expect($props)
        ->toBeArray()
        ->toHaveKey('name', 'overridden') // Should use attribute value
        ->toHaveKey('count', 5)
        ->toHaveKey('attributes')
        ->not->toHaveKey('extra'); // Should exclude non-property keys

    expect($props['attributes'])
        ->toBeInstanceOf(ComponentAttributeBag::class);
});

it('gets reserved names for standard mode', function () {
    $names = ['when', 'lazy', 'wire:model.live', 'other'];
    $reserved = Utils::getReservedNames(Component::STANDARD, $names);

    expect($reserved)
        ->toBeArray()
        ->toBe(['when']);
});

it('gets reserved names for livewire mode', function () {
    $names = ['when', 'lazy', 'wire:model.live', '@click', 'other'];
    $reserved = Utils::getReservedNames(Component::LIVEWIRE, $names);

    expect($reserved)
        ->toBeArray()
        ->toContain('when', 'lazy', 'wire:model.live', '@click')
        ->not->toContain('other');
});

it('resolves slots from data', function () {
    $data = [
        '__laravel_slots' => [
            '__default' => 'Default content',
            'header' => 'Header content',
            'footer' => 'Footer content',
        ],
    ];

    $slots = Utils::resolveSlots($data);

    expect($slots)
        ->toBeArray()
        ->toHaveKey('slot', 'Default content')
        ->toHaveKey('header', 'Header content')
        ->toHaveKey('footer', 'Footer content')
        ->not->toHaveKey('__default');
});

it('gets trait names from class', function () {
    $traitClass = new class {
        use \Illuminate\Support\Traits\Conditionable;
    };

    $traitNames = Utils::getTraitNames($traitClass::class);

    expect($traitNames)
        ->toBeArray()
        ->toHaveKey('conditionable');
});

it('calls hooks on component', function () {
    $component = new class {
        public bool $mountCalled = false;
        public bool $renderingCalled = false;

        public function mount(): void
        {
            $this->mountCalled = true;
        }

        public function rendering(): void
        {
            $this->renderingCalled = true;
        }
    };

    Utils::callHooks($component, 'mount');
    Utils::callHooks($component, 'rendering');

    expect($component->mountCalled)->toBeTrue();
    expect($component->renderingCalled)->toBeTrue();
});

it('calls hooks with arguments', function () {
    $component = new class {
        public string $receivedName = '';
        public int $receivedCount = 0;

        public function mount(string $name, int $count): void
        {
            $this->receivedName = $name;
            $this->receivedCount = $count;
        }
    };

    Utils::callHooks($component, 'mount', ['name' => 'test', 'count' => 42, 'extra' => 'ignored']);

    expect($component->receivedName)->toBe('test');
    expect($component->receivedCount)->toBe(42);
});

it('calls trait hooks when they exist', function () {
    // Create a component that uses a trait with a hook method
    $component = new class {
        use \Illuminate\Support\Traits\Conditionable;
        
        public bool $mountCalled = false;
        public bool $traitHookCalled = false;

        public function mount(): void
        {
            $this->mountCalled = true;
        }

        public function mountConditionable(): void
        {
            $this->traitHookCalled = true;
        }
    };

    Utils::callHooks($component, 'mount');

    expect($component->mountCalled)->toBeTrue();
    expect($component->traitHookCalled)->toBeTrue();
});

it('handles empty data gracefully', function () {
    expect(Utils::resolveSlots(['__laravel_slots' => ['__default' => null]]))->toBe(['slot' => null]);
    expect(Utils::getReservedNames(Component::STANDARD, []))->toBe([]);
    expect(Utils::getTraitNames(Component::class))->toBeArray();
});

it('handles non-existent methods gracefully', function () {
    $component = new class {};

    // Should not throw exception
    Utils::callHooks($component, 'nonExistentMethod');
    
    expect(true)->toBeTrue(); // Test passes if no exception thrown
});