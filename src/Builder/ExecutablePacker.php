<?php

namespace Phnet\Builder;

class ExecutablePacker
{
    public static function pack(string $packDirectory, string $mainProject, string $as, string $toDirectory = 'out'){
        $packDirectory = (new \SplFileInfo($packDirectory))->getRealPath();
        $outPhar = $packDirectory.DIRECTORY_SEPARATOR.$toDirectory.DIRECTORY_SEPARATOR.$as.'.phar';
        $outDir = dirname($outPhar);
        $projects = [];

        if(!is_dir($outDir))
            mkdir($outDir, 0755, true);

        foreach (glob($packDirectory.'/*.phar') as $pharProject){
            $projects[basename($pharProject, '.phar')] = $pharProject;
        }

        $phar = new \Phar($outPhar);
        $phar->startBuffering();

        $globalManifest = new Manifest([
            'name' => $as,
        ]);

        foreach ($projects as $project => $path){
            $pharPath = "phar://{$path}";
            $manifest = json_decode(file_get_contents($pharPath."/manifest.json"), true);

            if($project == $mainProject)
                $globalManifest->entrypoint = $manifest['entrypoint'];

            foreach ($manifest['types'] as $type => $innerPath){
                $newInnerPath = $project.DIRECTORY_SEPARATOR.$innerPath;
                $phar->addFile($pharPath.DIRECTORY_SEPARATOR.$innerPath, $newInnerPath);
                $globalManifest->types[$type] = $newInnerPath;
            }

            foreach ($manifest['resources'] as $accessPath => $innerPath){
                $phar->addFile($pharPath.DIRECTORY_SEPARATOR.$innerPath, $innerPath);
                $globalManifest->resources[$accessPath] = $innerPath;
            }

            foreach ($manifest['files'] as $file){
                $globalManifest->files[] = $file;
                $fileDir = dirname($outDir.DIRECTORY_SEPARATOR.$file);
                if(!is_dir($fileDir))
                    mkdir($fileDir, 0755, true);

                if(!copy($packDirectory.DIRECTORY_SEPARATOR.$file, $outDir.DIRECTORY_SEPARATOR.$file)){
                    throw new \Exception("Fail copy file {$file}");
                }
            }
        }

        $phar->addFromString('manifest.json', json_encode($globalManifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        $phar->setStub(Templates::getExecutableStub($as));

        $phar->stopBuffering();
    }
}