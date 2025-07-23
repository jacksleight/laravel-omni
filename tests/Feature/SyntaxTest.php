<?php

use JackSleight\LaravelOmni\Support\Syntax;

it('generates basic class from blank input', function () {
    $info = (object) ['class' => 'App\\Omni\\Counter'];
    $input = '';
    $output = Syntax::generateClass($info, $input, []);

    $expected = <<<PHP
    namespace App\Omni;

    use JackSleight\LaravelOmni\Component;

    class Counter extends Component {}
    PHP;

    expect($output)->toBe($expected);
});

it('converts array syntax to class', function () {
    $info = (object) ['class' => 'App\\Omni\\Counter'];
    $input = <<<'PHP'
    [
        'count' => 0,
        'label',
        'items' => [1, 'a', 3],
        'active' => true,
        0 => 'indexed',
    ]
    PHP;
    $output = Syntax::generateClass($info, $input, []);

    $expected = <<<PHP
    namespace App\Omni;

    use JackSleight\LaravelOmni\Component;

    class Counter extends Component
    {
        public \$count;
        public \$label;
        public \$items;
        public \$active;
        public \$indexed;
        public function __construct()
        {
            \$this->count = 0;
            \$this->label = null;
            \$this->items = [1,'a',3];
            \$this->active = true;
            \$this->indexed = null;
        }
    }
    PHP;

    expect($output)->toBe($expected);
});

it('converts anonymous class to named class', function () {
    $info = (object) ['class' => 'App\\Omni\\Helper'];
    $input = <<<'PHP'
    class
    {
        public \$count = 10;

        public function process() {
            return 'done';
        }
    }
    PHP;
    $output = Syntax::generateClass($info, $input, []);

    $expected = <<<'PHP'
    namespace App\Omni;

    use JackSleight\LaravelOmni\Component;

    class Helper extends Component
    {
        public \$count = 10;

        public function process() {
            return 'done';
        }
    }
    PHP;

    expect($output)->toBe($expected);
});

it('handles empty array', function () {
    $info = (object) ['class' => 'App\\Omni\\Empty'];
    $input = '[]';
    $output = Syntax::generateClass($info, $input, []);

    $expected = <<<PHP
    namespace App\Omni;

    use JackSleight\LaravelOmni\Component;

    class Empty extends Component
    {
        public function __construct()
        {}
    }
    PHP;

    expect($output)->toBe($expected);
});

it('includes additional use statements when provided', function () {
    $info = (object) ['class' => 'App\\Omni\\Counter'];
    $input = "['count' => 0]";
    $uses = ['Illuminate\\Support\\Collection', 'App\\Models\\User'];
    $output = Syntax::generateClass($info, $input, $uses);

    $expected = <<<PHP
    namespace App\Omni;

    use JackSleight\LaravelOmni\Component;
    use Illuminate\Support\Collection;
    use App\Models\User;

    class Counter extends Component
    {
        public \$count;
        public function __construct()
        {
            \$this->count = 0;
        }
    }
    PHP;

    expect($output)->toBe($expected);
});

it('throws exception for unsupported syntax', function () {
    $info = (object) ['class' => 'App\\Omni\\Test'];
    $input = 'function test() { return true; }';

    expect(fn () => Syntax::generateClass($info, $input))
        ->toThrow(InvalidArgumentException::class, 'Unsupported syntax for class generation.');
});
