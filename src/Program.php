<?php

namespace PharBuilder;

use SplFileInfo;

class Program
{
    public static function main($argv = [])
    {
        switch ($argv[1]) {
            case 'build':
                $options = getopt('o:');
                $director = new BuildDirector((new SplFileInfo('.'))->getRealPath(), $options['o']);
                $director->buildRelease();
                break;
            case 'restore':
                // todo: Реализовать
                break;
            case 'pack':
                $options = getopt('o:');
                $director = new BuildDirector('/tmp', $options['o']);
                $builder = $director->buildPackage();

                $manifest = $builder->getManifest();

                $packagePath = "/tmp/package/{$manifest->name}.phar";
                $packagePhar = new \Phar($packagePath);
                $packagePhar->startBuffering();
                $packagePhar->buildFromDirectory($builder->getBuildDirectory());
                $packagePhar->stopBuffering();

                rmdir($builder->getBuildDirectory());
                $manager = new PackageManager([
                    'source' => $builder->getConfig()['source']
                ]);
                $manager->uploadPackage($manifest->name, $manifest->version, $packagePath);
                break;
            default:
                throw new \Exception('Command not found');
        }
    }
}