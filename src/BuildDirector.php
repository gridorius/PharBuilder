<?php

namespace PharBuilder;

class BuildDirector
{
    protected $builder;

    public function __construct($folder, $buildDirectory = null)
    {
        $this->builder = new Builder($folder, $buildDirectory);
    }

    public function buildRelease(): Builder{
        return $this
            ->builder
            ->buildProjectReferences(true)
            ->buildPackageReferences()
            ->buildResources()
            ->buildPhar();
    }

    public function buildPackage(): Builder{
        return $this
            ->builder
            ->buildProjectReferences()
            ->buildResources()
            ->buildPhar();
    }
}