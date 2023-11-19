<?php

namespace Phnet\Builder;

class DependencyFinder
{
    protected array $read = [];
    protected array $depends = [];
    protected array $externalReferences = [];

    protected $requireModules = [];

    protected array $sources = [];

    public function __construct(string $projectFolder)
    {
        $config = ProjectConfig::getConfig($projectFolder);
        $this->depends = $config['packageReferences'] ?? [];
        $this->sources = $config['sources'] ?? [];
        $this->externalReferences = $config['externalReferences'] ?? [];

        if (!empty($config['projectReferences']))
            $this->readRecursive($config, $projectFolder);
    }

    protected function readRecursive(array $config, string $folder): DependencyFinder
    {
        foreach ($config['projectReferences'] as $projectReference) {
            $projectConfigPath = realpath($folder . DIRECTORY_SEPARATOR . $projectReference);
            if (!empty($this->read[$projectConfigPath]))
                continue;

            $this->read[$projectConfigPath] = true;
            $projectConfig = ProjectConfig::parseFromPath($projectConfigPath);

            if (!empty($projectConfig['packageReferences']))
                foreach ($projectConfig['packageReferences'] as $package => $version)
                    $this->depends[$package] = max($this->depends[$package], $version);

            if (!empty($projectConfig['sources']))
                foreach ($projectConfig['sources'] as $source)
                    $this->sources[] = $source;

            if (!empty($projectConfig['externalReferences']))
                foreach ($projectConfig['externalReferences'] as $reference)
                    $this->externalReferences[] = $reference;

            if (!empty($projectConfig['requiredModules']))
                foreach ($projectConfig['requiredModules'] as $reference)
                    $this->requireModules[] = $reference;

            $this->readRecursive($projectConfig, dirname($projectConfigPath));
        }

        return $this;
    }

    public function getDepends(): array
    {
        return $this->depends;
    }

    public function getSources(): array
    {
        return $this->sources;
    }

    public function getExternalDepends(): array
    {
        return $this->externalReferences;
    }
}