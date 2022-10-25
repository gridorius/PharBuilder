<?php

namespace PharBuilder;

use FilesystemIterator;
use Phar;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

class Builder
{
    protected $folder;
    protected $config;
    protected $buildDirectory;
    protected $buildName;
    protected $phar;

    public function __construct($folder){
        $this->folder = $folder;
        $this->config = $this->getConfig();
        $this->createPhar();
        $this->loadIncludes();
    }

    public function getConfig(){
        return json_decode(file_get_contents($this->folder.'/build.config.json'), true);
    }

    public function createPhar(){
        $this->buildName = ($this->config['outName'] ?? $this->config['name'] ?? 'build') . '.phar';
        if($this->config['outDir']){
            $this->buildDirectory = $this->folder.DIRECTORY_SEPARATOR.$this->config['outDir'];
        }else{
            $this->buildDirectory = $this->folder . '/build';
        }

        if(!is_dir($this->buildDirectory)){
            mkdir($this->buildDirectory);
        }

        $pharPath = $this->buildDirectory.DIRECTORY_SEPARATOR.$this->buildName;

        $this->phar = new Phar($pharPath, 0, $this->buildName);
        $this->phar->startBuffering();
    }

    public function loadIncludes(){
        if($this->config['files']){
            foreach ($this->config['files'] as $path){
                $this->phar->addFile($this->folder.DIRECTORY_SEPARATOR.$path, $path);
            }
        }

        if($this->config['directories']){
            foreach ($this->config['directories'] as $path){
                $this->phar->buildFromIterator(
                    new RecursiveIteratorIterator(
                        new RecursiveDirectoryIterator($this->folder.DIRECTORY_SEPARATOR.$path, FilesystemIterator::SKIP_DOTS)
                    ),
                    $this->folder
                );
            }
        }
    }

    public function build(){
        $include = [];

        $finder = new RecursiveFinder();

        foreach ($finder->find($this->folder, "/\.php/") as $path){
            $pharPath = str_replace($this->folder.'/', '', $path);
            $include[] = $pharPath;
            $this->phar->addFile($path, $pharPath);
        }

        $this->phar->setStub($this->makeStub($include));
        $this->phar->stopBuffering();
    }

    public function makeStub($includes){
        $projectName = $this->config['name'];
        $stub = "<?php".PHP_EOL;
        $stub.= "Phar::mapPhar('{$this->buildName}');".PHP_EOL;
        $stub.= "const PROGRAM_ROOT = \"phar://{$this->buildName}\";".PHP_EOL;
        $stub.= "set_include_path(PROGRAM_ROOT);".PHP_EOL;

        foreach ($includes as $include){
            $stub.= "include '{$include}';".PHP_EOL;
        }

        $stub.= "if(class_exists({$projectName}\Program::class))". PHP_EOL .
            "(new {$projectName}\Program)->main(\$argv);" .PHP_EOL;
        $stub.= "__HALT_COMPILER();".PHP_EOL;

        return $stub;
    }
}