<?php

namespace Phnet\Builder;

use Phnet\Builder\Manifests\ProjectManifest;
use Phnet\Builder\Manifests\SingleManifest;
use Phnet\Builder\Structure\PharBuilder;

class ExecutablePacker
{
    public static function pack(string $packDirectory, string $mainProject, string $as, string $toDirectory = 'out'){
        $packDirectory = (new \SplFileInfo($packDirectory))->getRealPath();
        $outPhar = $packDirectory.DIRECTORY_SEPARATOR.$toDirectory.DIRECTORY_SEPARATOR.$as.'.phar';
        $outDir = dirname($outPhar);
        $projects = [];

        if(!is_dir($outDir))
            mkdir($outDir, 0755, true);

        $mainAssembly = require $packDirectory.DIRECTORY_SEPARATOR.$mainProject.'.phar';
        $projects = [
            $mainProject => $mainAssembly
        ];
        static::loadDepends($packDirectory, $mainAssembly, $projects);

        $globalManifest = new SingleManifest();
        if($mainAssembly->hasEntrypoint())
            $globalManifest->entrypoint = $mainAssembly->getEntrypoint();

        $phar = new \Phar($outPhar);
        $phar->startBuffering();


        foreach ($projects as $assembly){
            $manifest = $assembly->getManifest();
            $pharPath = $assembly->getPath();
            $name = $assembly->getName();
            $directory = $name.DIRECTORY_SEPARATOR;
            $pharDirectoryPath = $pharPath.DIRECTORY_SEPARATOR;
            foreach ($manifest['types'] as $type => $innerPath){
                $newInnerPath = $directory.$innerPath;
                $manifest['types'][$type] = $newInnerPath;
                $phar->addFile($pharDirectoryPath.$innerPath, $newInnerPath);
            }

            foreach ($manifest['resources'] as $accessPath => $innerPath){
                $manifest['resources'][$accessPath] = $innerPath;
                $phar->addFile($pharDirectoryPath.$innerPath, $innerPath);
            }

            foreach ($manifest['files'] as $file){
                $fileDir = dirname($outDir.DIRECTORY_SEPARATOR.$file);
                if(!is_dir($fileDir))
                    mkdir($fileDir, 0755, true);

                if(!copy($packDirectory.DIRECTORY_SEPARATOR.$file, $outDir.DIRECTORY_SEPARATOR.$file)){
                    throw new \Exception("Fail copy file {$file}");
                }
            }

            $globalManifest->assemblies[] = $manifest;
        }
        $phar->stopBuffering();
        PharBuilder::buildSingle($phar, $globalManifest);
    }

    protected static function loadDepends(string $directory, $assembly, &$depends = []){
        foreach ($assembly->getDepends() as $depend => $version)
            if(!$depends[$depend]){
                $subAssembly = $depends[$depend] = require $directory.DIRECTORY_SEPARATOR.$depend.'.phar';
                if($subAssembly->hasDepends())
                    static::loadDepends($directory, $subAssembly, $depends);
            }

        return $depends;
    }
}