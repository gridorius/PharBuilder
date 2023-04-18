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

    public function __construct($folder)
    {
        $this->folder = $folder;
        $this->config = $this->getConfig();

        $this->buildName = ($this->config['outName'] ?? $this->config['name'] ?? 'build') . '.phar';
        if ($this->config['outDir']) {
            $this->buildDirectory = $this->folder . DIRECTORY_SEPARATOR . $this->config['outDir'];
        } else {
            $this->buildDirectory = $this->folder . '/build';
        }

        $this->createPhar();
        $this->loadIncludes();
    }

    public function getConfig()
    {
        return json_decode(file_get_contents($this->folder . '/build.config.json'), true);
    }

    public function createPhar()
    {
        if (!is_dir($this->buildDirectory)) {
            mkdir($this->buildDirectory, 0755, true);
        }

        $pharPath = $this->buildDirectory . DIRECTORY_SEPARATOR . $this->buildName;

        $this->phar = new Phar($pharPath, 0, $this->buildName);
        $this->phar->startBuffering();
    }

    public function loadIncludes()
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
        $include = [];
        $finder = new RecursiveFinder();
        $pattern = $this->config['pattern'] ?? "/\.php/";
        foreach ($finder->find($this->folder, $pattern) as $path) {
            $pharPath = str_replace($this->folder . '/', '', $path);
            $include[] = $pharPath;
            $this->phar->addFile($path, $pharPath);
        }

        $this->phar->setStub($this->makeStub($include));
        $this->phar->stopBuffering();
    }

    public function makeStub($includes)
    {
        $projectName = $this->config['name'];
        $start = $this->config['start'];
        $stub = "<?php" . PHP_EOL;
        $stub .= "Phar::mapPhar('{$this->buildName}');" . PHP_EOL;
        $stub .= "const PHAR_ROOT = \"phar://{$this->buildName}\";" . PHP_EOL;
        $stub .= "set_include_path(PHAR_ROOT);" . PHP_EOL;

        foreach ($includes as $include) {
            $stub .= "include '{$include}';" . PHP_EOL;
        }

        $stub .= $start;
        $stub .= "__HALT_COMPILER();" . PHP_EOL;

        return $stub;
    }
}