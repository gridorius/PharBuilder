<?php

namespace Phnet\Builder;

use Exception;
use Phar;
use Phnet\Builder\FIleScanning\Scanner;
use Phnet\Core\Resources;

class Builder
{
    /** @var Phar */
    protected $phar;
    protected $projectPath;
    protected $config;
    protected $pipeline = [];
    protected $manifest;
    protected $projectFiles;

    public function __construct(string $projectPath, array $config)
    {
        if (!is_dir($projectPath))
            throw new \Exception("Directory {$projectPath} not found");

        $this->projectPath = $projectPath;
        $this->projectFiles = Scanner::scanFilesWithoutSubprojects($projectPath);
        $this->config = $config;
        $this->manifest = new Manifest($config);
    }

    public function useTypeFiles($prefix = ''): Builder
    {
       $this->pipeline[] = function () use ($prefix) {
            foreach ($this->projectFiles->filterFiles("/\.php/", $this->config['exclude'] ?? []) as $path => $subPath) {
                $this->buildLibFile($path, $subPath, $prefix);
            }
        };

        return $this;
    }

    public function getPackageReferences(): ?array{
        return key_exists('packageReferences', $this->config) ? $this->config['packageReferences'] : [];
    }

    public function useFiles(): Builder{
        if(empty($this->config['files']))
            return $this;

        $this->pipeline[] = function ($outDir) {
            $resources = [];
            foreach ($this->config['files'] as $files) {
                $include = $files['include'];
                $exclude = $files['exclude'] ?? [];
                $exclude[] = "/\.proj\.json$/";
                $exclude[] = "/" . preg_quote($outDir, '/') . "/";

                try {
                    foreach ($this->projectFiles->filterFiles($include, $exclude) as $sourcePath => $subPath){
                        $target = $outDir . DIRECTORY_SEPARATOR . $subPath;
                        $targetDir = dirname($target);
                        $resources[] = [$targetDir, $sourcePath, $subPath, $target];
                    }
                } catch (Exception $ex) {
                    echo "Directory {$this->projectPath} regex {$include}";
                    throw $ex;
                }
            }

            foreach ($resources as $resource) {
                [$targetDir, $sourcePath, $subPath, $target] = $resource;

                if (!is_dir($targetDir))
                    mkdir($targetDir, 0755, true);


                if (!copy($sourcePath, $target))
                    throw new Exception("Не удалось копировать файл: {$sourcePath} > {$target}");

                $this->manifest->files[] = $subPath;
                $this->manifest->hashes[$subPath] = hash_file('sha256', $sourcePath);

                echo "Copy file {$sourcePath} to {$target}" . PHP_EOL;
            }
        };

        return $this;
    }

    public function useInclude(): Builder{
        if(empty($this->config['include-php']))
            return $this;

        $this->pipeline[] = function($outDir){
            foreach ($this->config['include-php'] as $path) {
                $globalPath = $this->projectPath . DIRECTORY_SEPARATOR . $path;
                if(!file_exists($path))
                    throw new Exception("include file \"{$globalPath}\" not exist");

                $this->phar->addFile($path, $globalPath);
            }
        };
    }

    public function useResources(): Builder{
        if (empty($this->config['resources']))
            return $this;

        $this->pipeline[] = function ($outDir) {
            foreach ($this->config['resources'] as $resource) {
                $include = $resource['include'];
                $exclude = $resource['exclude'] ?? [];
                $exclude[] = "/\.proj\.json$/";
                $exclude[] = "/" . preg_quote((new \SplFileInfo($outDir))->getRealPath(), '/') . "/";

                try {
                    foreach ($this->projectFiles->filterFiles($include, $exclude) as $sourcePath => $subPath){
                        $target = 'resources/' . $subPath;
                        $this->phar->addFile($sourcePath, $target);
                        $this->manifest->resources[$subPath] = $target;
                        $this->manifest->hashes[$subPath] = hash_file('sha256', $sourcePath);
                        echo "Resource file {$sourcePath} added" . PHP_EOL;
                    }
                } catch (Exception $ex) {
                    echo "Directory {$this->projectPath} regex {$include}".PHP_EOL;
                    throw $ex;
                }
            }
        };

        return $this;
    }

    private function createManifest()
    {
        $this->phar->addFromString('manifest.json', json_encode($this->manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    }

    private function makeStub(): string
    {
        $stubPath = $this->projectPath.DIRECTORY_SEPARATOR.'stub.php';
        if(is_file($stubPath))
            return file_get_contents($stubPath);

        return
        <<<STUB_CODE
        <?php
        Phar::mapPhar();
        __HALT_COMPILER();
        STUB_CODE;
    }

    private function makeIncludedStub(): string{
        return
            <<<STUB_CODE
        <?php
        Phar::mapPhar();
        require 'phar://'.__FILE__.'/loader.php';
        __HALT_COMPILER();
        STUB_CODE;
    }

    public function addProjectReferences(array $references){
        $this->manifest->pharDepends = array_merge($this->manifest->pharDepends, $references);
    }

    public function addProjectReference(string $name, string $version){
        $this->manifest->pharDepends[$name] = $version;
    }

    public function build(string $outDir){
        $this->runBuild($outDir, function ($outDir){
            $this->phar->setStub($this->makeStub());
        });

    }

    public function buildIncludedLibrary(string $outDir){
       $this->runBuild($outDir, function (){
           $this->phar->addFromString('loader.php', Resources::get('included/loader.php')->getContent());
           $this->phar->setStub($this->makeIncludedStub());
       });
    }

    private function runBuild(string $outDir, \Closure $action): void{
        $this->initPhar($outDir);
        foreach ($this->pipeline as $pipe){
            $pipe($outDir);
        }
        $action($outDir);
        $this->createManifest();
        $this->phar->stopBuffering();
    }

    private function initPhar(string $outDir){
        $pharName = $outDir.DIRECTORY_SEPARATOR.$this->config['name'].'.phar';
        if(!is_dir(dirname($pharName)))
            mkdir(dirname($pharName), 0755, true);

        $this->phar = new Phar($pharName);
        $this->phar->startBuffering();
    }

    private function buildLibFile($path, $innerPath, $prefix = '')
    {
        $types = EntityFinder::findByTokens($path);
        if(count($types) > 0){
            foreach ($types as $type){
                $typePath = 'types/' .$prefix. $innerPath;
                $this->manifest->types[$type] = $typePath;
                echo "Found type {$type}".PHP_EOL;
                $this->phar->addFile($path, $typePath);
            }
        }
    }
}