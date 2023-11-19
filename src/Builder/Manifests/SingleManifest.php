<?php

namespace Phnet\Builder\Manifests;

class SingleManifest
{
    public $type = 'single';
    public array $assemblies = [];
    public array $depends = [];

    public array $entrypoint = [];
}