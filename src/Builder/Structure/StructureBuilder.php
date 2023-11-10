<?php

namespace Phnet\Builder\Structure;

use Exception;
use Phnet\Builder\EntityFinder;
use Phnet\Builder\FIleScanning\Scanner;
use Phnet\Builder\Manifest;

class StructureBuilder
{
    protected string $projectPath;
    protected $projectFiles = [];
    protected array $config = [];

    protected array $pipes = [];

    public function __construct(string $projectPath, array $config)
    {
        if (!is_dir($projectPath))
            throw new Exception("Directory {$projectPath} not exist");

        $this->projectPath = $projectPath;
        $this->projectFiles = Scanner::scanFilesWithoutSubprojects($projectPath);
        $this->config = $config;
    }

    public function useTypeFiles($prefix = ''): StructureBuilder
    {
        $this->pipes[] = function (Structure $structure) use ($prefix) {
            foreach ($this->projectFiles->filterFiles("/\.php/", $this->config['exclude'] ?? []) as $path => $subPath) {
                $this->buildLibFile($structure, $path, $subPath, $prefix);
            }
        };

        return $this;
    }

    private function buildLibFile(Structure $structure, $path, $innerPath, $prefix = '')
    {
        $types = EntityFinder::findByTokens($path);
        if (count($types) > 0) {
            foreach ($types as $type) {
                $typePath = $prefix . $innerPath;
                $structure->manifest->types[$type] = $typePath;
                $structure->types[$path] = $typePath;
                echo "Found type {$type}" . PHP_EOL;
            }
        }
    }

    public function useFiles(): StructureBuilder
    {
        if (empty($this->config['files']))
            return $this;

        $this->pipes[] = function (Structure $structure) {
            foreach ($this->config['files'] as $files) {
                $include = $files['include'];
                $exclude = $files['exclude'] ?? [];

                try {
                    foreach ($this->projectFiles->filterFiles($include, $exclude) as $sourcePath => $subPath) {
                        $structure->files[$sourcePath] = $subPath;
                        $structure->manifest->files[] = $subPath;
                        $structure->manifest->hashes[$subPath] = hash_file('sha256', $sourcePath);
                    }
                } catch (Exception $ex) {
                    echo "Directory {$this->projectPath} regex {$include}";
                    throw $ex;
                }
            }
        };
        return $this;
    }

    public function useInclude(): StructureBuilder
    {
        if (empty($this->config['include-php']))
            return $this;

        $this->pipes[] = function (Structure $structure) {
            foreach ($this->config['include-php'] as $path) {
                $globalPath = $this->projectPath . DIRECTORY_SEPARATOR . $path;
                if (!file_exists($path))
                    throw new Exception("include file \"{$globalPath}\" not exist");

                $structure->includePhp[$globalPath] = $path;
                $structure->manifest->include[$globalPath] = $path;
            }
        };
    }

    public function useResources(): StructureBuilder
    {
        if (empty($this->config['resources']))
            return $this;

        $this->pipes[] = function (Structure $structure) {
            foreach ($this->config['resources'] as $resource) {
                $include = $resource['include'];
                $exclude = $resource['exclude'] ?? [];
                try {
                    foreach ($this->projectFiles->filterFiles($include, $exclude) as $sourcePath => $subPath) {
                        $structure->resources[$sourcePath] = $subPath;
                        $structure->manifest->resources[$subPath] = $subPath;
                    }
                } catch (Exception $ex) {
                    echo "Directory {$this->projectPath} regex {$include}" . PHP_EOL;
                    throw $ex;
                }
            }
        };

        return $this;
    }

    public function build(): Structure
    {
        $structure = new Structure($this->config);
        foreach ($this->pipes as $pipe) {
            $pipe($structure);
        }

        return $structure;
    }
}