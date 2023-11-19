<?php
Phar::mapPhar();
return new class('phar://' . __FILE__) {
    protected $path;
    protected $manifest;
    protected $rawManifest;

    public function __construct($path)
    {
        $this->path = $path;
        $manifestPath = $path . '/manifest.json';
        if (!file_exists($manifestPath))
            throw new Exception('Manifest not exist');
        $this->rawManifest = $this->manifest = json_decode(file_get_contents($manifestPath), true);
        $this->preparePaths();
        $this->loadAllDepends();
    }

    private function preparePaths()
    {
        foreach ($this->manifest['types'] as $type => $path)
            $this->manifest['types'][$type] = $this->path . DIRECTORY_SEPARATOR . $path;

        foreach ($this->manifest['resources'] as $resource => $path)
            $this->manifest['resources'][$resource] = $this->path . DIRECTORY_SEPARATOR . $path;

        foreach ($this->manifest['include'] as $key => $path)
            $this->manifest['include'][$key] = $this->path . DIRECTORY_SEPARATOR . $path;
    }

    public function hasDepends(): bool
    {
        return !empty($this->manifest['depends']);
    }

    public function getDepends(): array
    {
        return $this->manifest['depends'];
    }

    public function getInclude(): array
    {
        return $this->manifest['include'];
    }

    public function loadAllDepends()
    {
        $assemblies[] = $this;
        if ($this->hasDepends())
            $this->loadDependsRecursive($this->getDepends(), $assemblies);

        foreach ($assemblies as $assembly)
            $assembly->registerTypes();

        foreach ($assemblies as $assembly)
            $assembly->preloadClasses();

        foreach ($assemblies as $assembly)
            foreach ($assembly->getInclude() as $path)
                require $path;
    }

    private function loadDependsRecursive(array $depends, array &$assemblies = [])
    {
        $dir = dirname(__FILE__);
        foreach ($depends as $name => $version) {
            $dependAssembly = require $dir . DIRECTORY_SEPARATOR . $name . '.phar';
            $assemblies[] = $dependAssembly;
            if ($dependAssembly->hasDepends())
                $dependAssembly->loadDependsRecursive($dependAssembly->getDepends(), $assemblies);
        }
    }

    public function registerTypes()
    {
        spl_autoload_register(function (string $entity) {
            if (key_exists($entity, $this->manifest['types']))
                require $this->manifest['types'][$entity];
        });
    }

    public function preloadClasses()
    {
        foreach ($this->manifest['types'] as $type => $path)
            class_exists($type);
    }
};
__HALT_COMPILER();
