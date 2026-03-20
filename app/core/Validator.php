<?php

namespace App\Core;

class Validator
{
    public static function required($value): bool
    {
        return trim((string) $value) !== '';
    }

    public static function email($value): bool
    {
        return (bool) filter_var($value, FILTER_VALIDATE_EMAIL);
    }
}
