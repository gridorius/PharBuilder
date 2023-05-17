<?php

namespace Phnet\Builder;

use Phnet\Core\Assembly;

class BuildDirector
{
    protected $builder;

    public function __construct($folder, $buildDirectory = null)
    {
        $this->builder = new Builder($folder, $buildDirectory);

        if (is_dir($buildDirectory))
            rmdir($buildDirectory);
    }

    public function executable()
    {
        $this->builder->executable();
    }

    public function build(): Builder
    {
        $this
            ->builder
            ->buildProjectReferences()
            ->buildPackageReferences()
            ->buildFiles()
            ->buildResources()
            ->buildPhar();

        if(!copy(Assembly::getPath('Phnet.Core.phar'),
            $this->builder->getBuildDirectory().DIRECTORY_SEPARATOR.'Phnet.Core.phar'))
            throw new \Exception('can not copy core');
        return $this->builder;
    }

    public function buildPackage(): Builder
    {
        return $this
            ->builder
            ->withoutPackages()
            ->buildProjectReferences()
            ->buildFiles()
            ->buildResources()
            ->buildPhar();
    }
}