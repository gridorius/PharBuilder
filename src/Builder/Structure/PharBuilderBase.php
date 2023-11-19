<?php

namespace Phnet\Builder\Structure;

use Exception;
use Phar;

abstract class PharBuilderBase
{
    protected $structure;

    public function __construct(Structure $structure)
    {
        $this->structure = $structure;
    }

    public static function getPharPath(string $outDir, string $name): string
    {
        return $outDir . DIRECTORY_SEPARATOR . $name . '.phar';
    }

    protected function createPhar(string $outDir): Phar
    {
        $pharName = static::getPharPath($outDir, $this->structure->manifest->name);
        if (!is_dir(dirname($pharName)))
            mkdir(dirname($pharName), 0755, true);

        return new Phar($pharName);
    }

    protected function makeDirectory(string $directory)
    {
        mkdir($directory, 0755, true);
    }

    protected function createManifest(Phar $projectPhar)
    {
        $projectPhar->addFromString(
            'manifest.json',
            json_encode($this->structure->manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
        );
    }

    protected function copyFiles(string $outDir)
    {
        foreach ($this->structure->files as $realPath => $outPath) {
            $fulOutPath = $outDir . DIRECTORY_SEPARATOR . $outPath;
            $directory = dirname($fulOutPath);
            if (!is_dir($directory))
                $this->makeDirectory($directory);
            if (!copy($realPath, $fulOutPath))
                throw new Exception("Copy file failed: {$realPath} > {$outPath}");
        }
    }

    protected function getFileIterator(): \ArrayIterator
    {
        $files = [];
        foreach ($this->structure->resources as $realPath => $outPath)
            $files[$outPath] = $realPath;
        foreach ($this->structure->types as $realPath => $outPath)
            $files[$outPath] = $realPath;
        foreach ($this->structure->includePhp as $realPath => $outPath)
            $files[$outPath] = $realPath;

        return new \ArrayIterator($files);
    }
}