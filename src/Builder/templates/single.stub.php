<?php
use Phnet\Core\Assembly;

Phar::mapPhar();
$assembly = new class('phar://' . __FILE__){
    protected $path;
    protected $manifest;
    protected $assemblies = [];
    public function __construct($path)
    {
        $this->path = $path;
        $manifestPath = $path.'/manifest.json';
        if(!file_exists($manifestPath))
            throw new Exception('Manifest not exist');
        $this->manifest = json_decode(file_get_contents($manifestPath), true);

        foreach ($this->manifest['assemblies'] as $manifest)
            $this->assemblies[$manifest['name']] = $this->createAssemblyClass('phar://' . __FILE__, $manifest);
    }

    public function getType(): string{
        return $this->manifest['type'];
    }

    public function getAssemblies(): array{
        return $this->assemblies;
    }

    public function getDepends(): array{
        return $this->manifest['depends'];
    }

    public function hasEntrypoint(): bool{
        return !empty($this->manifest['entrypoint']);
    }
    public function run($argv){
        [$class, $method] = $this->manifest['entrypoint'];
        $class::$method($argv);
    }

    private function createAssemblyClass($path, $manifest){
        return new class($path, $manifest){
            protected $path;
            protected $manifest;
            public function __construct($path, $manifest)
            {
                $this->path = $path;
                $this->manifest = $manifest;
                $this->preparePaths();
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
    }
};


require '/var/lib/phnet/Phnet.Core.phar';
Assembly::initSingle(__DIR__, $assembly);
Assembly::entrypoint($argv);
__HALT_COMPILER();
