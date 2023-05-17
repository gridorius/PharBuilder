<?php

namespace Phnet\Core;

use Phar;

class Assembly
{
    public static function getPath(string ...$additional): string{
        return dirname(Phar::running(false)).
            (!empty($additional) ? DIRECTORY_SEPARATOR.
                implode('/', $additional)
                : '');
    }
}