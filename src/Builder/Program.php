<?php

namespace Phnet\Builder;

use Exception;
use Phnet\Builder\Console\MultilineOutput;
use Phnet\Builder\Console\Options;
use Phnet\Builder\Source\SourceManager;
use Phnet\Core\Resources;

class Program
{
    /** @var PhnetService */
    protected static $service;
    /** @var PackageManager */
    protected static $packageManager;
    /** @var SourceManager */
    protected static $sourceManager;

    public static function main($argv = [])
    {
        $config = Resources::get('configuration.php')->include();
        static::$packageManager = new PackageManager($config);
        static::$sourceManager = new SourceManager($config);
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
                    throw new Exception('Command not found');
            }
        } catch (Exception $ex) {
            fwrite(STDERR, $ex->getMessage() . PHP_EOL);
            exit(1);
        }
    }

    protected static function build($argv)
    {
        $options = new Options();
        $options->required(['o', 'p', 'e']);
        $options->single(['i'], ['debug', 'library']);

        $options->parse($argv);
        $options = $options->getOptions();

        $buildDirectory = $options['o'] ?? 'out';
        $director = new BuildDirector(static::$packageManager, realpath(empty($options['p']) ? '.' : $options['p']));

        $start = time();
        $output = MultilineOutput::getInstance();
        $output->setParams([
            'time' => function () use ($start) {
                return time() - $start;
            }
        ]);
        $output->createRow()->update('Build project {time}');

        if (!empty($options['e']))
            $director->buildSingle($buildDirectory, $options['e']);
        else if (!empty($options['library']))
            $director->buildLibrary($buildDirectory);
        else
            $director->build($buildDirectory);

        if (!empty($options['i']))
            static::$service->makeIndexFile($buildDirectory, $director->getName());
    }

    protected static function makeIndexFile($argv)
    {
        if (!$argv[2])
            throw new Exception('Directory not settled');

        if (!$argv[3])
            throw new Exception('Phar name not settled');

        static::$service->makeIndexFile($argv[2], $argv[3]);
    }

    protected static function login($argv)
    {
        $source = $argv[2];
        if (empty(trim($source)))
            throw new Exception('source not set');

        echo 'Enter login:';
        $login = fgets(STDIN);
        echo PHP_EOL;
        echo 'Enter password:';
        $password = fgets(STDIN);
        echo PHP_EOL;

        static::$sourceManager->getSource($source)->auth($login, $password);
    }

    protected static function restore($argv)
    {
        $start = time();
        $output = MultilineOutput::getInstance();
        $output->setParams([
            'time' => function () use ($start) {
                return time() - $start;
            }
        ]);
        $output->createRow()->update('Restore project {time}');
        $reader = new DependencyFinder('.');
        $dependsRow = $output->createRow('depends')->update('Restore depends {time}');
        static::$packageManager
            ->loadDepends($reader->getDepends(), static::$sourceManager->getSources($reader->getSources()));
        $dependsRow->clearSubdata();
        $externalDependsRow = $output->createRow('externalDepends')->update('Restore external depends {time}');
        static::$packageManager->loadExternalDepends($reader->getExternalDepends());
        $externalDependsRow->clearSubdata();
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
            throw new Exception('Source not settled');


        if (!static::$sourceManager->isAuthorized($options['s']))
            throw new Exception('unauthorized');

        echo 'Start build package' . PHP_EOL;
        $director = new BuildDirector(static::$packageManager, realpath('.'));
        $packResult = $director->buildPackage();
        echo 'Start uploading' . PHP_EOL;

        $packageId = static::$packageManager->uploadPackage(
            static::$sourceManager->getCredentials($options['s']),
            $packResult,
            !key_exists('private', $options),
        );
        echo "Package uploaded #{$packageId}" . PHP_EOL;
    }
}