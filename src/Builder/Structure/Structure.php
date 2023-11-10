<?php

namespace Phnet\Builder\Structure;

use Phnet\Builder\Manifest;

class Structure
{
    public array $resources = [];
    public array $types = [];
    public array $files = [];
    public array $includePhp = [];
    public $manifest;

    public function __construct(array $config)
    {
        $this->manifest = new Manifest($config);
    }
}