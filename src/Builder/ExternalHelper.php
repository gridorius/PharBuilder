<?php

namespace Phnet\Builder;

class ExternalHelper
{
    public static function getExternalPackageNameAndVersion(array $reference)
    {
        switch ($reference['type']) {
            case 'github':
                $name = $reference['proj']['name'] ?? $reference['owner'] . $reference['repo'];
                $version = $reference['proj']['version'] ?? $reference['version'] ?? '*.*.*';
                return [$name, $version];
        }
    }
}