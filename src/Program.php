<?php

namespace PharBuilder;

use SplFileInfo;

class Program
{
    public static function main($argv = [])
    {
        try {
            switch ($argv[1]) {
                case 'build':
                    static::build($argv);
                    break;
                case 'make:index':
                    static::makeIndexFile($argv);
                    break;
                case 'restore':
                    static::restore($argv);
                    break;
                case 'package':
                    static::package($argv);
                    break;
                default:
                    throw new \Exception('Command not found');
            }
        } catch (\Exception $ex) {
            fwrite(STDERR, $ex->getMessage().PHP_EOL);
            exit(1);
        }
    }

    protected static function build($argv)
    {
        $options = new Options($argv);
        $options->required([
            'o'
        ]);

        $options->parse();
        $options = $options->getOptions();

        $director = new BuildDirector(ProjectConfig::findConfig('.'), $options['o']);
        $director->buildRelease();
    }

    protected static function makeIndexFile($argv)
    {
        if (!$argv[2])
            throw new \Exception('Directory not settled');

        if (!$argv[3])
            throw new \Exception('Phar file not settled');

        $indexPath = (new SplFileInfo($argv[2]))->getRealPath();
        $pharPath = $indexPath . '/' . $argv[3];

        if (!file_exists($pharPath))
            throw new \Exception("{$pharPath} not found");

        file_put_contents($indexPath . '/index.php', Templates::getIndex($argv[3]));
    }

    protected static function restore($argv)
    {
        $config = ProjectConfig::getConfig('.');
        $depends = array_map(function ($package, $version) {
            return [$package, $version];
        }, array_keys($config['packageReferences']), $config['packageReferences']);
        $sources = $config['packageSources'];
        $manager = new PackageManager($sources);

        $manager->loadDepends($depends);
    }

    protected static function package($argv)
    {
        $options = new Options($argv);
        $options->required([
            's', 'p'
        ], [
            'private'
        ]);

        $options->parse();
        $options = $options->getOptions();

        if (empty($options['s']))
            throw new \Exception('Source not settled');

        if (empty($options['p']))
            throw new \Exception('Password not settled');

        echo 'Start build package' . PHP_EOL;

        $buildDir = '/tmp/' . uniqid('build_');
        mkdir($buildDir, 0755, true);
        $director = new BuildDirector(ProjectConfig::findConfig('.'), $buildDir);
        $builder = $director->buildPackage();

        $packageConfig = ProjectConfig::getConfig('.');
        $packageManifest = [
            'name' => $packageConfig['name'],
            'version' => $packageConfig['version'],
            'packageReferences' => $packageConfig['packageReferences'] ?? []
        ];

        echo 'Wrap package' . PHP_EOL;;

        $tempname = uniqid('pack_') . '.phar';
        $packagePath = "/tmp/{$tempname}";
        $packagePhar = new \Phar($packagePath);
        $packagePhar->startBuffering();
        $packagePhar->buildFromDirectory($builder->getBuildDirectory());
        $packagePhar->addFromString('package.manifest.json', json_encode($packageManifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        $packagePhar->stopBuffering();

        echo 'Start uploading' . PHP_EOL;

        array_map('unlink', glob($builder->getBuildDirectory() . '/*'));
        rmdir($builder->getBuildDirectory());

        $packageId = PackageManager::uploadPackage(
            $packageManifest['name'],
            $packageManifest['version'],
            $packagePath,
            $options['s'],
            $options['p'],
            !key_exists('private', $options)
        );
        unlink($packagePath);
        echo "Package uploaded #{$packageId}" . PHP_EOL;;
    }
}