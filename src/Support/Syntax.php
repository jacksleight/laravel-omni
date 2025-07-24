<?php

namespace JackSleight\LaravelOmni\Support;

use Illuminate\Support\Str;

class Syntax
{
    public static function generateClass($info, $code, $uses = [])
    {
        $code = rtrim(trim($code), ';');

        if (empty($code)) {
            [$extends, $body] = static::generateClassFromEmpty();
        } elseif (Str::startsWith($code, '[') && Str::endsWith($code, ']')) {
            [$extends, $body] = static::generateClassFromArray($code);
        } elseif (Str::startsWith($code, 'class') || Str::startsWith($code, 'trait')) {
            [$extends, $body] = static::generateClassFromAnonymous($code);
        } else {
            throw new \InvalidArgumentException('Unsupported syntax for class generation.');
        }

        $namespace = Str::beforeLast($info->class, '\\');
        $basename = Str::afterLast($info->class, '\\');

        array_unshift($uses, 'JackSleight\LaravelOmni\Component');

        $uses = 'use '.implode(";\nuse ", $uses).';';

        if (empty($body)) {
            return "namespace $namespace;\n\n$uses\n\nclass $basename extends $extends {}";
        }

        return "namespace $namespace;\n\n$uses\n\nclass $basename extends $extends\n{\n$body\n}";
    }

    protected static function generateClassFromEmpty()
    {
        return ['Component', ''];
    }

    protected static function generateClassFromArray($code)
    {
        $code = trim($code, '[]');
        if (empty(trim($code))) {
            return ['Component', "    public function __construct()\n    {}"];
        }

        $parts = preg_split('/,(?=(?:[^"\']*(?:"[^"]*"|\'[^\']*\'))*[^"\']*$)(?![^()[\]]*[)\]])/', $code);
        $parts = collect($parts)
            ->map(fn($part) => trim($part))
            ->filter()
            ->map(function($part) {
                if (preg_match('/^(.+?)\s*=>\s*(.+)$/', $part, $match)) {
                    $key = trim($match[1], '\'"');
                    if (is_numeric($key)) {
                        $name = trim($match[2], '\'"');
                        return ["public \$$name;", "\$this->$name = null;"];
                    }
                    return ["public \$$key;", "\$this->$key = {$match[2]};"];
                }
                $name = trim($part, '\'"');
                return ["public \$$name;", "\$this->$name = null;"];
            });

        $props = $parts
            ->pluck(0)
            ->implode("\n    ");
        $assis = $parts
            ->pluck(1)
            ->implode("\n        ");
        
        return ['Component', "    $props\n    public function __construct()\n    {\n        $assis\n    }"];
    }

    protected static function generateClassFromAnonymous($code)
    {
        $pattern = '/class(?:\s*extends\s*(\w*))?\s*\{(.*)\}/is';

        if (! preg_match($pattern, $code, $match)) {
            throw new \InvalidArgumentException('Invalid anonymous class syntax.');
        }

        return [$match[1] ?: 'Component', trim($match[2], "\n")];
    }
}
