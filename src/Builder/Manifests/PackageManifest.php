<?php

namespace Phnet\Builder\Manifests;

class PackageManifest
{
    public string $name;

    public string $version;

    public array $depends = [];

    public array $externalDepends = [];

    public array $requiredModules = [];

    public array $hashes = [];

    public function __construct(string $name, string $version, array $depends = [])
    {
        $this->name = $name;
        $this->version = $version;
        $this->depends = $depends;
    }

    public function setExternalDepends(array $externalDepends)
    {
        $this->externalDepends = $externalDepends;
    }
}