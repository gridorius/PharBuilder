<?php

namespace Phnet\Builder;

class RecursiveProjectReader
{
    protected $folder;
    protected $mainConfig;
    protected $configs = [];
    protected $packageReferences = [];

    public function __construct(string $projectFolder)
    {
        $this->folder = $projectFolder;
        $this->configs[] = $this->mainConfig = ProjectConfig::getConfig($projectFolder);
        $this->readRecursive($this->mainConfig, $projectFolder);
    }

    protected function readRecursive(array $config, string $folder){
        foreach ($config['projectReferences'] as $projectReference){
            $projectConfigPath = $folder.DIRECTORY_SEPARATOR.$projectReference;
            $this->configs[] = $projectConfig = ProjectConfig::parseFromPath($projectConfigPath);

            foreach ($projectConfig['packageReferences'] as $package => $version){
                $this->packageReferences[$package] = max($this->packageReferences[$package], $version);
            }

            $references[$projectConfig['name']] = $projectConfig['version'] ?? 0;
            $this->readRecursive($projectConfig, dirname($projectConfigPath));
        }
    }

    public function getConfigs(){
        return $this->configs;
    }

    public function getPackageReferences(){
        return $this->packageReferences;
    }
}