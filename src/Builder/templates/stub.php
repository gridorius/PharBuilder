<?php
Phar::mapPhar();
return new class('phar://' . __FILE__){
    protected $path;
    protected $manifest;
    protected $rawManifest;
    public function __construct($path)
    {
        $this->path = $path;
        $manifestPath = $path.'/manifest.json';
        if(!file_exists($manifestPath))
            throw new Exception('Manifest not exist');
        $this->rawManifest = $this->manifest = json_decode(file_get_contents($manifestPath), true);
        $this->preparePaths();
    }

    public function getName(): string{
        return $this->manifest['name'];
    }

    public function getPath(): string{
        return $this->path;
    }

    public function getFiles(): array{
        return $this->manifest['files'];
    }

    public function getType(): string{
        return $this->manifest['type'];
    }

    public function getManifest(): array{
        return $this->rawManifest;
    }

    private function preparePaths(){
        foreach ($this->manifest['types'] as $type => $path)
            $this->manifest['types'][$type] = $this->path.DIRECTORY_SEPARATOR.$path;

        foreach ($this->manifest['resources'] as $resource => $path)
            $this->manifest['resources'][$resource] = $this->path.DIRECTORY_SEPARATOR.$path;

        foreach ($this->manifest['include'] as $key => $path)
            $this->manifest['include'][$key] = $this->path.DIRECTORY_SEPARATOR.$path;
    }

    public function hasEntrypoint(): bool{
        return !empty($this->manifest['entrypoint']);
    }

    public function getEntrypoint(): array{
        return $this->manifest['entrypoint'];
    }

    public function run($argv){
        [$class, $method] = $this->manifest['entrypoint'];
        $class::$method($argv);
    }

    public function getTypes(): array{
        return $this->manifest['types'];
    }

    public function getResources(): array{
        return $this->manifest['resources'];
    }

    public function getInclude(): array{
        return $this->manifest['include'];
    }

    public function hasDepends(): bool{
        return !empty($this->manifest['depends']);
    }

    public function getDepends(): array{
        return $this->manifest['depends'];
    }

    public function registerTypes(){
        spl_autoload_register(function(string $entity){
            if(key_exists($entity, $this->manifest['types']))
                require $this->manifest['types'][$entity];
        });
    }

    public function preloadClasses(){
        foreach ($this->manifest['types'] as $type => $path)
            class_exists($type);
    }
};
__HALT_COMPILER();
