<?php

namespace Phnet\Builder\FIleScanning;

class Result
{
    protected $files = [];
    public function __construct(array $files)
    {
        $this->files = $files;
    }

    public function getFiles(): array
    {
        return $this->files;
    }

    public function get(string $name){
        return $this->files[$name];
    }

    public function filterFiles(string $findPattern, ?array $exclude = []): array
    {
        $filtered = [];
        foreach ($this->files as $path => $subPath) {
            if (preg_match($findPattern, $path))
                $filtered[$path] = $subPath;
        }

        if (empty($exclude)) return $filtered;

        $excluded = [];
        foreach ($filtered as $path => $subPath) {
            foreach ($exclude as $pattern) {
                if (!preg_match($pattern, $path))
                    $excluded[$path] = $subPath;
            }
        }

        return $excluded;
    }

}