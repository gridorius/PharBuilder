<?php

namespace Phnet\Builder\Source;

class SourcePackage
{
    protected $source;
    protected $packageName;
    protected $packageVersion;
    public function __construct(Source $source, string $name, string $version)
    {
        $this->source = $source;
        $this->packageName = $name;
        $this->packageVersion = $version;
    }

    public function download(string $path){
        $data = $this->source->downloadPackage($this->packageName, $this->packageVersion);
        file_put_contents($path, $data);
    }
}