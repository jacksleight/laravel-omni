<?php

namespace JackSleight\LaravelOmni\Support;

use Illuminate\Support\Str;

class Syntax
{
    public static function generateClass($info, $code, $uses = [])
    {
        $code = rtrim(trim($code), ';');

        if (strlen($code) === 0) {
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

        return $body
            ? "namespace $namespace;\n\n$uses\n\nclass $basename extends $extends\n{\n$body\n}"
            : "namespace $namespace;\n\n$uses\n\nclass $basename extends $extends {}";
    }

    protected static function generateClassFromEmpty()
    {
        return ['Component', ''];
    }

    protected static function generateClassFromArray($code)
    {
        $elements = static::extractArrayElements($code);

        $properties = [];
        $assignments = [];

        foreach ($elements as $element) {
            [$property, $assignment] = static::parseArrayElement($element);
            if ($property) {
                $properties[] = $property;
                $assignments[] = $assignment;
            }
        }

        if (empty($properties)) {
            $body = "    public function __construct()\n    {}";
        } else {
            $properties = implode("\n    ", $properties);
            $assignments = implode("\n        ", $assignments);
            $body = "    $properties\n    public function __construct()\n    {\n        $assignments\n    }";
        }

        return ['Component', $body];
    }

    protected static function generateClassFromAnonymous($code)
    {
        $pattern = '/class(?:\s*extends\s*(\w*))?\s*\{(.*)\}/is';

        if (! preg_match($pattern, $code, $match)) {
            throw new \InvalidArgumentException('Invalid anonymous class syntax.');
        }

        return [$match[1] ? $match[1] : 'Component', trim($match[2], "\n")];
    }

    protected static function extractArrayElements($code)
    {
        $tokens = token_get_all("<?php $code");
        $elements = [];
        $current = [];
        $depth = 0;
        $parenDepth = 0;
        $inArray = false;

        foreach ($tokens as $token) {
            if (is_array($token)) {
                if ($inArray && $token[0] !== T_WHITESPACE && $token[0] !== T_COMMENT) {
                    $current[] = $token;
                }
            } else {
                if ($token === '[') {
                    $depth++;
                    if ($depth === 1) {
                        $inArray = true;
                    } else {
                        $current[] = $token;
                    }
                } elseif ($token === ']') {
                    $depth--;
                    if ($depth === 0) {
                        if (! empty($current)) {
                            $elements[] = $current;
                            $current = [];
                        }
                        break;
                    } else {
                        $current[] = $token;
                    }
                } elseif ($token === '(') {
                    $parenDepth++;
                    if ($inArray) {
                        $current[] = $token;
                    }
                } elseif ($token === ')') {
                    $parenDepth--;
                    if ($inArray) {
                        $current[] = $token;
                    }
                } elseif ($token === ',' && $depth === 1 && $parenDepth === 0) {
                    if (! empty($current)) {
                        $elements[] = $current;
                    }
                    $current = [];
                } elseif ($inArray) {
                    $current[] = $token;
                }
            }
        }

        if (! empty($current)) {
            $elements[] = $current;
        }

        return $elements;
    }

    protected static function parseArrayElement($element)
    {
        if (empty($element)) {
            return [null, null];
        }

        $arrowIndex = static::findArrowToken($element);

        if ($arrowIndex !== null) {
            $key = static::cleanString(static::tokensToString(array_slice($element, 0, $arrowIndex)));
            $value = static::cleanString(static::tokensToString(array_slice($element, $arrowIndex + 1)));

            if (is_numeric($key)) {
                return ["public \$$value;", "\$this->$value = null;"];
            }

            return [
                "public \$$key;",
                "\$this->$key = ".static::tokensToString(array_slice($element, $arrowIndex + 1)).';',
            ];
        }

        $propName = static::cleanString(static::tokensToString($element));

        return ["public \$$propName;", "\$this->$propName = null;"];
    }

    protected static function findArrowToken($tokens)
    {
        foreach ($tokens as $i => $token) {
            if (is_array($token) && $token[0] === T_DOUBLE_ARROW) {
                return $i;
            }
        }

        return null;
    }

    protected static function cleanString($string)
    {
        $string = trim($string);
        if ((str_starts_with($string, '"') && str_ends_with($string, '"')) ||
            (str_starts_with($string, "'") && str_ends_with($string, "'"))) {
            return substr($string, 1, -1);
        }

        return $string;
    }

    protected static function tokensToString($tokens)
    {
        $result = '';
        $lastWasComma = false;

        foreach ($tokens as $token) {
            if (is_array($token)) {
                $text = $token[1];
                if ($token[0] === T_WHITESPACE && $lastWasComma) {
                    $result .= ' ';
                    $lastWasComma = false;
                } else {
                    $result .= $text;
                }
            } else {
                $result .= $token;
                $lastWasComma = ($token === ',');
            }
        }

        return trim($result);
    }
}
