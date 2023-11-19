<?php

namespace Phnet\Builder;

use Phnet\Builder\Manifests\SingleManifest;
use Phnet\Builder\Structure\PharBuilder;
use Phnet\Builder\Structure\Structure;
use Phnet\Builder\Structure\StructureBuilder;

class BuildDirector
{
    /** @var PackageManager */
    protected $packageManager;
    protected $folder;
    /** @var Structure[] */
    protected array $structures = [];
    protected array $structured = [];
    protected $mainConf;

    public function __construct(PackageManager $manager, string $projectFolder)
    {
        $this->packageManager = $manager;
        $this->folder = $projectFolder;
        $this->mainConf = ProjectConfig::getConfig($projectFolder);
    }

    public function getName()
    {
        return $this->mainConf['name'];
    }

    public function build(string $outDir)
    {
        $this->prepareProject($this->folder, $this->mainConf);
        foreach ($this->structures as $structure) {
            $builder = new PharBuilder($structure);
            $this->unpackExternalReferences($structure->externalReferences, $outDir, $structure->manifest->depends);
            $builder->build($outDir);
            $this->unpackPackageReferences($structure->packageReferences, $outDir);
        }
    }

    public function buildSingle(string $outDir, string $name)
    {
        $pharName = PharBuilder::getPharPath($outDir, $name);
        if (!is_dir(dirname($pharName)))
            mkdir(dirname($pharName), 0755, true);
        $phar = new \Phar($pharName);
        $singleManifest = new SingleManifest();
        $mainStructure = $this->prepareProject($this->folder, $this->mainConf);
        if (!empty($mainStructure->manifest->entrypoint))
            $singleManifest->entrypoint = $mainStructure->manifest->entrypoint;
        foreach ($this->structures as $structure) {
            $builder = new PharBuilder($structure);
            $this->unpackExternalReferences($structure->externalReferences, $outDir, $structure->manifest->depends);
            $builder->buildSingleProject($outDir, $phar);
            $singleManifest->assemblies[] = $structure->manifest;
            $this->unpackPackageReferences($structure->packageReferences, $outDir);
        }

        PharBuilder::buildSingle($phar, $singleManifest);
    }

    public function buildLibrary(string $outDir)
    {
        $this->prepareProject($this->folder, $this->mainConf);
        $baseStructure = array_pop($this->structures);

        $builder = new PharBuilder($baseStructure);
        $this->unpackExternalReferences($baseStructure->externalReferences, $outDir, $baseStructure->manifest->depends);
        $builder->buildLibrary($outDir);
        $this->unpackPackageReferences($baseStructure->packageReferences, $outDir);

        foreach ($this->structures as $structure) {
            $builder = new PharBuilder($structure);
            $this->unpackExternalReferences($structure->externalReferences, $outDir, $structure->manifest->depends);
            $builder->build($outDir);
            $this->unpackPackageReferences($structure->packageReferences, $outDir);
        }
    }

    protected function unpackPackageReferences(array $references, string $outDir)
    {
        if (empty($references)) return;
        $depends = [];
        foreach ($references as $package => $version) {
            $localPackage = $this->packageManager->findLocal($package, $version);
            $manifest = (new \Phar($localPackage['path']))->getMetadata();
            foreach ($manifest->depends as $name => $ver)
                $depends[$name] = $ver;

            if ($localPackage) {
                $this->packageManager->unpack($localPackage['path'], $outDir);
            } else {
                throw new \Exception("Local package {$package}({$version}) not found");
            }
        }

        $this->unpackPackageReferences($depends, $outDir);
    }

    protected function unpackExternalReferences(array $references, string $outDir, array &$depends): void
    {
        foreach ($references as $reference) {
            if ($this->packageManager->hasExternal($reference['type'])) {
                $this->packageManager->getExternal($reference['type'])->build($reference, $outDir, $depends);
            } else {
                throw new \Exception("Build handler for type {$reference['type']} not found");
            }
        }
    }

    public function buildPackage(): array
    {
        $mainStructure = $this->prepareProject($this->folder, $this->mainConf);
        $outDir = '/tmp/' . uniqid('build_');
        mkdir($outDir, 0755, true);
        foreach ($this->structures as $structure) {
            $builder = new PharBuilder($structure);
            $builder->build($outDir);
        }

        $depends = array_reduce($this->structures, function ($carry, Structure $structure) {
            foreach ($structure->packageReferences as $name => $version)
                $carry[$name] = max($carry[$name], $version);
            return $carry;
        }, []);

        $externalReferences = array_reduce($this->structures, function ($carry, Structure $structure) {
            foreach ($structure->externalReferences as $reference)
                $carry[] = $reference;
            return $carry;
        }, []);

        $requiredModules = array_reduce($this->structures, function ($carry, Structure $structure) {
            foreach ($structure->requiredModules as $module)
                $carry[] = $module;
            return $carry;
        }, []);

        $result = $this->packageManager->pack($outDir, $mainStructure->manifest, $depends, $externalReferences, array_unique($requiredModules));
        array_map('unlink', glob($outDir . DIRECTORY_SEPARATOR . '*'));
        rmdir($outDir);

        return $result;
    }

    private function prepareSubProjects(string $parentProjectPath, array $projectReferences): array
    {
        $references = [];
        foreach ($projectReferences as $projectReference) {
            $projectConfigPath = realpath($parentProjectPath . DIRECTORY_SEPARATOR . $projectReference);
            if (!empty($this->structured[$projectConfigPath]))
                continue;

            $this->structured[$projectConfigPath] = true;
            $projectConfig = ProjectConfig::parseFromPath($projectConfigPath);
            $references[$projectConfig['name']] = $projectConfig['version'] ?? 0;
            $this->prepareProject(dirname($projectConfigPath), $projectConfig);
        }

        return $references;
    }

    private function prepareProject(string $projectPath, array $config): Structure
    {
        $structureBuilder = new StructureBuilder((new \SplFileInfo($projectPath))->getRealPath(), $config);
        $references = $this->prepareSubProjects($projectPath, $config['projectReferences'] ?? []);
        $structureBuilder->useTypeFiles();
        $structureBuilder->useInclude();
        $structureBuilder->useFiles();
        $structureBuilder->useResources();
        $structureBuilder->addProjectReferences($references);
        return $this->structures[] = $structureBuilder->build();
    }
}