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

    public function get(string $name)
    {
        return $this->files[$name];
    }

    public function filterFiles(array $params): array
    {
        $filtered = !empty($params['includeRegex'])
            ? $this->findByRegex($params['includeRegex'])
            : $this->findByFNPattern($params['include']);

        if (empty($params['exclude']) && empty($params['excludeRegex'])) return $filtered;

        return !empty($params['excludeRegex'])
            ? $this->excludeByRegex($params['excludeRegex'], $filtered)
            : $this->excludeByFNPatterns($params['exclude'], $filtered);
    }

    protected function findByRegex(string $pattern): array
    {
        $filtered = [];
        foreach ($this->files as $path => $subPath) {
            if (preg_match($pattern, $subPath))
                $filtered[$path] = $subPath;
        }
        return $filtered;
    }

    protected function excludeByRegex(array $patterns, array $files): array{
        $excluded = [];
        foreach ($files as $path => $subPath) {
            foreach ($patterns as $pattern) {
                if (!preg_match($pattern, $subPath))
                    $excluded[$path] = $subPath;
            }
        }

        return $excluded;
    }

    protected function findByFNPattern(string $pattern): array
    {
        $filtered = [];
        foreach ($this->files as $path => $subPath) {
            if (fnmatch($pattern, $subPath))
                $filtered[$path] = $subPath;
        }
        return $filtered;
    }

    protected function excludeByFNPatterns(array $patterns, array $files): array{
        $excluded = [];
        foreach ($files as $path => $subPath) {
            foreach ($patterns as $pattern) {
                if (!fnmatch($pattern, $subPath))
                    $excluded[$path] = $subPath;
            }
        }

        return $excluded;
    }
}