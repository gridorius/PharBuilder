<?php

namespace Phnet\Builder;

class BuildDirector
{
    protected $folder;
    /** @var Builder[] */
    protected $builders = [];
    protected $mainConf;

    public function __construct(string $projectFolder)
    {
        $this->folder = $projectFolder;
        $this->mainConf = ProjectConfig::getConfig($projectFolder);
    }

    public function getName(){
        return $this->mainConf['name'];
    }

    public function build(string $outDir){
        $this->prepareMainProject($this->folder, $this->mainConf);
        foreach ($this->builders as $builder){
            $builder->build($outDir);
            $this->buildPackageReferences($builder, $outDir);
        }
    }

    public function buildIncludedLibrary(string $outDir){
        $this->prepareMainProject($this->folder, $this->mainConf);
        foreach ($this->builders as $builder){
            $builder->buildIncludedLibrary($outDir);
            $this->buildPackageReferences($builder, $outDir);
        }
    }

    public function buildSingle(string $outDir){
        $this->prepareMainProject($this->folder, $this->mainConf);
        foreach ($this->builders as $builder){
            $builder->build($outDir);
            $this->buildPackageReferences($builder, $outDir);
        }
    }

    protected function buildPackageReferences(Builder $builder, string $outDir){
        foreach ($builder->getPackageReferences() as $package => $version){
            $localPackage = PackagePacker::findLocal($package, $version);

            if($localPackage){
                PackagePacker::unpack($localPackage, $outDir);
            }else{
                throw new \Exception("Local package {$package} {$version} not found");
            }
        }
    }

    public function buildPackage(){
        $outDir = '/tmp/' . uniqid('build_');
        mkdir($outDir, 0755, true);
        foreach ($this->builders as $builder){
            $builder->build($outDir);
        }

        PackagePacker::pack($outDir, $this->mainConf);
    }

    private function buildSubProjects(string $parentProjectPath, array $projectReferences){
        $references = [];
        foreach ($projectReferences as $projectReference){
            $projectConfigPath = $parentProjectPath.DIRECTORY_SEPARATOR.$projectReference;
            $projectConfig = ProjectConfig::parseFromPath($projectConfigPath);
            $references[$projectConfig['name']] = $projectConfig['version'] ?? 0;
            $this->prepareProject(dirname($projectConfigPath), $projectConfig);
        }

        return $references;
    }

    private function prepareMainProject(string $projectPath, array $config){
        $this->prepareProject($projectPath, $config);
    }

    private function prepareProject(string $projectPath, array $config){
        $builder = new Builder((new \SplFileInfo($projectPath))->getRealPath(), $config);
        $references = $this->buildSubProjects($projectPath,$config['projectReferences'] ?? []);
        $builder->useTypeFiles();
        $builder->useInclude();
        $builder->useFiles();
        $builder->useResources();
        $builder->addProjectReferences($references);

        $this->builders[] = $builder;
        return $builder;
    }
}