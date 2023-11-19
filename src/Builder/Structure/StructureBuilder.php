<?php

namespace Phnet\Builder\Structure;

use Exception;
use Phnet\Builder\Console\MultilineOutput;
use Phnet\Builder\Console\Row;
use Phnet\Builder\EntityFinder;
use Phnet\Builder\FIleScanning\Scanner;

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

    public function useTypeFiles(): StructureBuilder
    {
        $this->pipes[] = function (Structure $structure, Row $row) {
            if (!empty($this->config['build'])) {
                foreach ($this->config['build'] as $params)
                    $this->buildLibFilesFromFilter($params, $structure, $row);
            } else {
                $this->buildLibFilesFromFilter([
                    'include' => "*.php",
                    'exclude' => $this->config['exclude'] ?? []
                ], $structure, $row);
            }
        };

        return $this;
    }

    private function buildLibFilesFromFilter(array $filter, Structure $structure, Row $row)
    {
        foreach ($this->projectFiles->filterFiles($filter) as $path => $subPath) {
            $this->buildLibFile($structure, $path, $subPath, $row);
        }
    }

    private function buildLibFile(Structure $structure, $path, $innerPath, Row $row)
    {
        $types = EntityFinder::findByTokens($path);
        if (count($types) > 0) {
            foreach ($types as $type) {
                $structure->manifest->types[$type] = $innerPath;
                $structure->types[$path] = $innerPath;
                $row->addSubdata("Found type {$type}");
            }
        }
    }

    public function useFiles(): StructureBuilder
    {
        if (empty($this->config['files']))
            return $this;

        $this->pipes[] = function (Structure $structure) {
            foreach ($this->config['files'] as $files) {
                foreach ($this->projectFiles->filterFiles($files) as $sourcePath => $subPath) {
                    $structure->files[$sourcePath] = $subPath;
                    $structure->manifest->files[] = $subPath;
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
            foreach ($this->config['include-php'] as $params) {
                foreach ($this->projectFiles->filterFiles($params) as $sourcePath => $subPath) {
                    $structure->includePhp[$sourcePath] = $subPath;
                    $structure->manifest->include[] = $subPath;
                }
            }
        };

        return $this;
    }

    public function useResources(): StructureBuilder
    {
        if (empty($this->config['resources']))
            return $this;

        $this->pipes[] = function (Structure $structure) {
            foreach ($this->config['resources'] as $resource) {
                foreach ($this->projectFiles->filterFiles($resource) as $sourcePath => $subPath) {
                    $structure->resources[$sourcePath] = $subPath;
                    $structure->manifest->resources[$subPath] = $subPath;
                }
            }
        };

        return $this;
    }

    public function addProjectReferences(array $references)
    {
        $this->pipes[] = function (Structure $structure) use ($references) {
            $structure->manifest->depends = array_merge($structure->manifest->depends, $references);
        };
    }

    public function build(): Structure
    {
        $structure = new Structure($this->config);
        $structureRow = MultilineOutput::getInstance()
            ->createRow()
            ->update("Build {$structure->manifest->name}({$structure->manifest->version}) {time}");
        $stubPath = $this->projectPath . DIRECTORY_SEPARATOR . 'stub.php';
        if (is_file($stubPath))
            $structure->stub = $stubPath;

        foreach ($this->pipes as $pipe) {
            $pipe($structure, $structureRow);
        }

        return $structure;
    }
}