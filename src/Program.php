<?php

namespace PharBuilder;

use SplFileInfo;

class Program
{
    /** @var PhnetService */
    protected static $service;
    protected static $loginDir = '/etc/phnet/login';

    public static function main($argv = [])
    {
        static::$service = new PhnetService();
        try {
            switch ($argv[1]) {
                case 'build':
                    static::build($argv);
                    break;
                case 'login':
                    static::login($argv);
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
        $options = new Options();
        $options->required(['o', 'p']);
        $options->single(['i', 'e']);

        $options->parse($argv);
        $options = $options->getOptions();

        $buildDirectory = $options['o'] ?? 'out';
        $director = new BuildDirector(ProjectConfig::findConfig(empty($options['p']) ? '.' : $options['p']), $buildDirectory);
        if(!empty($options['e']))
            $director->executable();

        $builder = $director->buildRelease();

        if(!empty($options['i']))
            static::$service->makeIndexFile($buildDirectory, $builder->getBuildName());
    }

    protected static function makeIndexFile($argv)
    {
        if (!$argv[2])
            throw new \Exception('Directory not settled');

        if (!$argv[3])
            throw new \Exception('Phar file not settled');

        static::$service->makeIndexFile($argv[2], $argv[3]);
    }

    protected static function restore($argv)
    {
        $config = ProjectConfig::getConfig('.');
        $sources = $config['packageSources'];
        $manager = new PackageManager($sources);

        $manager->loadDepends($config['packageReferences']);
    }

    protected static function package($argv)
    {
        $options = new Options();
        $options->required([
            's'
        ], [
            'private'
        ]);

        $options->parse($argv);
        $options = $options->getOptions();

        if (empty($options['s']))
            throw new \Exception('Source not settled');

        $loginPath = static::$loginDir.'/'.md5($options['s']).'.json';
        if(!is_file($loginPath)){
            throw new \Exception('unauthorized');
        }

        $loginData = json_decode(file_get_contents($loginPath), true)['password'];

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
            $loginData['password'],
            !key_exists('private', $options),
            $packageManifest['packageReferences']
        );
        unlink($packagePath);
        echo "Package uploaded #{$packageId}" . PHP_EOL;;
    }

    protected static function login($argv){
        $options = new Options();
        $options->required([
            'p', 's'
        ]);

        $options->parse($argv);
        $options = $options->getOptions();

        if (empty($options['s']))
            throw new \Exception('Source not settled');

        if (empty($options['p']))
            throw new \Exception('Password not settled');

        $password = $options['p'];
        $source = $options['s'];

        if(!is_dir(static::$loginDir))
            mkdir(static::$loginDir, 0755, true);

        $filePath = static::$loginDir.'/'.md5($source).'.json';
        file_put_contents($filePath, json_encode([
            'source' => $source,
            'password' => $password
        ]));
    }
}