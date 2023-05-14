<?php

namespace PharBuilder;

class BuildDirector
{
    protected $builder;
    protected $isExecutable = false;

    public function __construct($folder, $buildDirectory = null)
    {
        $this->builder = new Builder($folder, $buildDirectory);
    }

    public function executable(){
        $this->builder->executable();
    }

    public function buildRelease(): Builder{
        return $this
            ->builder
            ->buildProjectReferences()
            ->buildPackageReferences()
            ->buildFiles()
            ->buildPhar();
    }

    public function buildPackage(): Builder{
        return $this
            ->builder
            ->withoutPackages()
            ->buildProjectReferences()
            ->buildFiles()
            ->buildPhar();
    }
}