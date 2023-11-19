<?php

namespace Phnet\Builder\Structure;

use Phnet\Builder\Manifests\ProjectManifest;

class Structure
{
    public array $resources = [];
    public array $types = [];
    public array $files = [];
    public ?string $stub = null;
    public array $includePhp = [];
    public array $packageReferences = [];
    public array $externalReferences = [];

    public array $requiredModules = [];
    public $manifest;

    public function __construct(array $config)
    {
        $this->manifest = new ProjectManifest($config);
        if(!empty($config['packageReferences']))
            $this->packageReferences = $config['packageReferences'];

        if(!empty($config['externalReferences']))
            $this->externalReferences = $config['externalReferences'];

        if(!empty($config['requiredModules']))
            $this->requiredModules = $config['requiredModules'];
    }

    public function setTypePrefix(string $prefix): void
    {
        foreach ($this->types as $key => $typePath)
            $this->types[$key] = $prefix . $typePath;

        foreach ($this->manifest->types as $key => $manifestTypePath)
            $this->manifest->types[$key] = $prefix . $manifestTypePath;
    }

    public function setResourcePrefix(string $prefix): void
    {
        foreach ($this->resources as $key => $resourcePath)
            $this->resources[$key] = $prefix . $resourcePath;

        foreach ($this->manifest->resources as $key => $manifestResourcePath)
            $this->manifest->resources[$key] = $prefix . $manifestResourcePath;
    }

    public function setIncludePrefix(string $prefix): void
    {
        foreach ($this->includePhp as $key => $includePath)
            $this->includePhp[$key] = $prefix . $includePath;

        foreach ($this->manifest->include as $key => $manifestIncludePath)
            $this->manifest->include[$key] = $prefix . $manifestIncludePath;
    }
}