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
    protected $depends = [];

    public function __construct($folder, $buildDirectory = null)
    {
        $this->folder = $folder;
        $this->config = $this->getConfig();

        $this->buildName = ($this->config['outName'] ?? $this->config['name'] ?? 'build') . '.phar';
        if ($buildDirectory) {
            $this->buildDirectory = $buildDirectory;
        } else if ($this->config['outDir']) {
            $this->buildDirectory = $this->folder . DIRECTORY_SEPARATOR . $this->config['outDir'];
        } else {
            $this->buildDirectory = $this->folder . '/build';
        }

        $this->createPhar();
        $this->loadIncludes();
    }

    protected function getConfig()
    {
        return json_decode(file_get_contents($this->folder . '/build.config.json'), true);
    }

    protected function buildDepends()
    {
        foreach ($this->config['depends'] as $path) {
            $subBuilder = new static($path, $this->buildDirectory);
            $subBuilder->build();

            $this->depends[] = $subBuilder->getBuildName() . '.phar';
        }
    }

    protected function createPhar()
    {
        if (!is_dir($this->buildDirectory)) {
            mkdir($this->buildDirectory, 0755, true);
        }

        $pharPath = $this->buildDirectory . DIRECTORY_SEPARATOR . $this->buildName;

        $this->phar = new Phar($pharPath, 0, $this->buildName);
        $this->phar->startBuffering();
    }

    public function getBuildName()
    {
        return $this->buildName;
    }

    protected function loadIncludes()
    {
        if ($this->config['files']) {
            foreach ($this->config['files'] as $path) {
                $sourcePath = $this->folder . DIRECTORY_SEPARATOR . $path;
                $filename = pathinfo($sourcePath, PATHINFO_FILENAME);
                copy($sourcePath, $this->buildDirectory . DIRECTORY_SEPARATOR . $filename);
            }
        }

        if ($this->config['directories']) {
            foreach ($this->config['directories'] as $path) {
                $directoryIterator = new RecursiveIteratorIterator(
                    new RecursiveDirectoryIterator($this->folder . DIRECTORY_SEPARATOR . $path, FilesystemIterator::SKIP_DOTS)
                );
                $directoryIterator->rewind();
                while ($directoryIterator->valid()) {
                    $innerDirPath = $this->buildDirectory . DIRECTORY_SEPARATOR . $directoryIterator->getSubPath();
                    if (!is_dir($innerDirPath))
                        mkdir($innerDirPath, 0755, true);

                    copy($directoryIterator->key(), $this->buildDirectory . DIRECTORY_SEPARATOR . $directoryIterator->getSubPathName());
                    $directoryIterator->next();
                }
            }
        }
    }

    public function build()
    {
        $finder = new RecursiveFinder();
        $pattern = $this->config['pattern'] ?? "/\.php/";
        foreach ($finder->find($this->folder, $pattern) as $path) {
            $innerPath = uniqid('include/include_') . '.php';
            $this->phar->addFile($path, $innerPath);
        }

        $this->phar->setStub($this->makeStub());
        $this->phar->stopBuffering();
    }

    protected function makeStub()
    {
        $stub = '<?php' . PHP_EOL;
        $stub .= "Phar::mapPhar('{$this->buildName}');" . PHP_EOL;
        $stub .= "\$pharRoot = \"phar://{$this->buildName}\";" . PHP_EOL;
        $stub .=
"foreach (glob(\$pharRoot.'/include/*.php') as \$filename)
{
    require \$filename;
}" . PHP_EOL;

        $stub .= $this->config['start'] . PHP_EOL;
        $stub .= "__HALT_COMPILER();" . PHP_EOL;

        return $stub;
    }
}