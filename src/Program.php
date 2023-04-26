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
        }catch (\Exception $ex){
            echo $ex->getMessage().PHP_EOL;
        }
    }

    protected static function build($argv)
    {
        $options = getopt('o:');
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

        file_put_contents($indexPath . '/index.php', IndexTemplate::get($argv[3]));
    }

    protected static function restore($argv)
    {
        $config = ProjectConfig::getConfig('.');
        $depends = array_map(function ($package, $version) {
            return [$package, $version];
        }, array_keys($config['packageReferences']), $config['packageReferences']);
        $sources = $config['packageSources'];
        $manager = new PackageManager($sources);

        while (count($depends) > 0) {
            [$name, $version] = array_shift($depends);

            if (PackageManager::findLocally($name, $version))
                continue;

            echo "Package {$name} {$version} not found locally, find in sources" .PHP_EOL;

            if ($foundData = $manager->findPackage($name, $version)) {
                echo "Package {$name} {$foundData['version']} foind in {$foundData['source']}";
                $localPath = PackageManager::savePackage($name, $foundData['version'], PackageManager::getPackageData($foundData['path']));
                $manifestPath = "phar://{$localPath}/manifest.json";
                if ($manifestRaw = file_get_contents($manifestPath)) {
                    $manifest = json_decode($manifestRaw, true);
                    foreach ($manifest['depends'] as $depend)
                        $depends[] = [$depend['name'], $depend['version']];
                } else {
                    throw new \Exception("Failed read manifest in {$manifestPath}");
                }
            } else {
                throw new \Exception('package not found');
            }
        }
    }

    protected static function package($argv)
    {
        $options = getopt('s:', [
            'private'
        ]);

        if (empty($options['s']))
            throw new \Exception('Source not settled');

        if (empty($options['p']))
            throw new \Exception('Password not settled');

        echo 'Enter password: ';
        $password = fgets(STDIN);

        echo 'Start build package' . PHP_EOL;

        $buildDir = '/tmp/' . uniqid('build_');
        mkdir($buildDir, 0755, true);
        $director = new BuildDirector(ProjectConfig::findConfig('.'), $buildDir);
        $builder = $director->buildPackage();

        echo 'Wrap package'. PHP_EOL;;

        $manifest = $builder->getManifest();
        $tempname = uniqid('pack_') . '.phar';
        $packagePath = "/tmp/{$tempname}";
        $packagePhar = new \Phar($packagePath);
        $packagePhar->startBuffering();
        $packagePhar->buildFromDirectory($builder->getBuildDirectory());
        $packagePhar->addFromString('manifest.json', json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        $packagePhar->stopBuffering();

        echo 'Start uploading' . PHP_EOL;

        array_map('unlink', glob($builder->getBuildDirectory() . '/*'));
        rmdir($builder->getBuildDirectory());

        $packageId = PackageManager::uploadPackage(
            $manifest->name,
            $manifest->version,
            $packagePath,
            $options['s'],
            $password,
            !key_exists('private', $options)
        );
        unlink($packagePath);
        echo "Package uploaded #{$packageId}" . PHP_EOL;;
    }
}