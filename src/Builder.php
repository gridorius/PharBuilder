<?php

namespace PharBuilder;

use FilesystemIterator;
use MongoDB\BSON\Timestamp;
use Phar;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

class Builder
{
    protected $name;
    protected $folder;
    protected $config;
    protected $buildDirectory;
    protected $buildName;
    protected $phar;
    protected $navigationPrefix;
    protected $manifest;
    protected $buildPipe = [];

    public function __construct(string $configPath, $buildDirectory = null)
    {
        $this->manifest = new Manifest();
        $this->folder = (new \SplFileInfo(dirname($configPath)))->getRealPath();
        $this->config = json_decode(file_get_contents($configPath), true);

        $this->manifest->name = $this->config['name'];
        $this->manifest->version = $this->config['version'];

        $this->name = $this->config['name'];
        $this->buildName = $this->config['name'] . '.phar';
        $this->navigationPrefix = "phar://{$this->buildName}";

        $this->buildDirectory = $buildDirectory ?? 'out';
    }

    public function getName()
    {
        return $this->name;
    }

    public function getBuildDirectory()
    {
        return $this->buildDirectory;
    }

    public function getManifest(): Manifest
    {
        return $this->manifest;
    }

    protected function createBuildDirectory(){
        if (!is_dir($this->buildDirectory)) {
            mkdir($this->buildDirectory, 0755, true);
        }

        $this->buildDirectory = (new \SplFileInfo($this->buildDirectory))->getRealPath();
    }

    protected function createPharFile()
    {
        $pharPath = $this->buildDirectory . DIRECTORY_SEPARATOR . $this->buildName;

        if (file_exists($pharPath))
            unlink($pharPath);

        $this->phar = new Phar($pharPath);
        $this->phar->startBuffering();
    }

    public function buildProjectReferences(bool $needBuildPackages = false): self
    {
        if (!key_exists('projectReferences', $this->config))
            return $this;

        $this->buildPipe[] = function () use ($needBuildPackages) {
            foreach ($this->config['projectReferences'] as $reference) {
                $subBuilder = new static($this->folder . DIRECTORY_SEPARATOR . $reference, $this->buildDirectory);
                $this->manifest->depends[] = new Depend($subBuilder->getName(), $subBuilder->getManifest()->version);
                if ($needBuildPackages)
                    $subBuilder
                        ->buildPackageReferences();

                $subBuilder
                    ->buildProjectReferences()
                    ->buildResources()
                    ->buildPhar();
            }
        };

        return $this;
    }

    public function buildPackageReferences()
    {
        if (!key_exists('packageReferences', $this->config))
            return $this;

        $this->buildPipe[] = function () {
            foreach ($this->config['packageReferences'] as $package => $version) {
                $this->manifest->depends[] = new Depend($package, $version);
            }

            $path = PackageManager::findLocally($package, $version);

            if (!$path)
                throw new \Exception("Package {$package} {$version} not found locally");

            PackageManager::unpackToBuild($path, $this->buildDirectory);
            echo "Package {$package} {$version} added to build" . PHP_EOL;
        };

        return $this;
    }

    public function buildResources()
    {
        if (!key_exists('embeddedResources', $this->config))
            return $this;

        $this->buildPipe[] = function () {
            foreach ($this->config['embeddedResources'] as $resourceConfiguration) {
                $include = $resourceConfiguration['include'];
                $exclude = $resourceConfiguration['exclude'] ?? [];
                $exclude[] = "/\.proj\.json$/";

                try {
                    $iterator = new \RegexIterator(
                        new RecursiveIteratorIterator(
                            new \RecursiveDirectoryIterator($this->folder, FilesystemIterator::SKIP_DOTS)
                        ), $include
                    );
                } catch (\Exception $ex) {
                    echo "Directory {$this->folder} regex {$include}";
                    throw $ex;
                }

                foreach ($iterator as $fileInfo) {
                    $sourcePath = $iterator->key();
                    foreach ($exclude as $ex)
                        if (preg_match($ex, $sourcePath))
                            continue 2;

                    $innerPath = str_replace($this->folder, '', $sourcePath);
                    $target = $this->buildDirectory . $innerPath;
                    $targetDir = dirname($target);
                    if (!is_dir($targetDir))
                        mkdir($targetDir, 0755, true);

                    if (!copy($sourcePath, $target))
                        throw new \Exception("Не удалось копировать файл: {$sourcePath} > {$target}");

                    $this->manifest->files[] = $innerPath;

                    echo "Copy file {$sourcePath} to {$target}" . PHP_EOL;
                }
            }
        };

        return $this;
    }

    public function buildPhar(): self
    {
        $this->createBuildDirectory();
        $this->createPharFile();

        $pattern = $this->config['pattern'] ?? "\.php$";

        if ($this->config['buildFolders']) {
            foreach ($this->config['buildFolders'] as $folder)
                $this->buildLibFolder($this->folder . DIRECTORY_SEPARATOR . $folder, "/{$pattern}/");
        } else {
            $this->buildLibFolder($this->folder, "/{$pattern}/");
        }

        foreach ($this->buildPipe as $build) {
            $build();
        }

        $this->createManifestFile();
        $this->createAutoloadFile();

        $this->phar->setStub($this->makeStub());
        $this->phar->stopBuffering();

        echo "Phar compiled to directory {$this->buildDirectory}" . PHP_EOL;
        return $this;
    }

    protected function buildLibFolder($folder, $pattern)
    {
        echo "Start build folder: {$folder}" . PHP_EOL;
        $findIterator = new \RegexIterator(new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($folder, FilesystemIterator::SKIP_DOTS)
        ), $pattern, \RecursiveRegexIterator::GET_MATCH);
        $findIterator->rewind();

        $withoutExcludeHandler = function ($findIterator, $path) {
            $innerPath = $findIterator->getSubPathName();
            $this->buildLibFile($path, $innerPath);
        };

        $withExcludeHandler = function ($findIterator, $path) {
            foreach ($this->config['exclude'] as $pattern) {
                if (preg_match($pattern, $path)) {
                    $findIterator->next();
                    return;
                }

                $innerPath = $findIterator->getSubPathName();
                $this->buildLibFile($path, $innerPath);
            }
        };

        $handler = key_exists('exclude', $this->config) ? $withExcludeHandler : $withoutExcludeHandler;

        while ($findIterator->valid()) {
            $path = $findIterator->key();
            $handler($findIterator, $path);
            $findIterator->next();
        }

        echo "Folder builded: {$folder}" . PHP_EOL;
    }

    protected function buildLibFile($path, $innerPath)
    {
        $content = php_strip_whitespace($path);
        $prepared = preg_replace("/^<\?(php)?/", '', $content);
        if (preg_match_all(
            Constants::NAMESPACE_REGEX,
            $prepared,
            $matches,
            PREG_SET_ORDER
        )) {
            foreach ($matches as $match) {
                $namespaceContent = $match['content_t1'] ?? $match['content_t2'] ?? '';
                preg_match_all(Constants::ENTITY_REGEX, $namespaceContent, $namespaceMatches, PREG_SET_ORDER);

                foreach ($namespaceMatches as $namespaceMatch) {
                    $entity = $match['namespace'] . '\\' . $namespaceMatch['name'];

                    $innerPath = 'lib/' . $innerPath;
                    $this->manifest->types[$entity] = $innerPath;

                    $this->phar->addFile($path, $innerPath);
                    echo "File {$path} added to library as {$innerPath};" . PHP_EOL;
                }
            }
        }
    }

    protected function createManifestFile()
    {
        $this->phar->addFromString('manifest.json', json_encode($this->manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    }

    protected function createAutoloadFile()
    {
        if (count($this->manifest->types) == 0)
            return;

        $autoloadString =
            <<< AUTOLOAD_STRING
        <?php
        \$navigation = json_decode(file_get_contents('{$this->navigationPrefix}/manifest.json'), true)['types'];
        spl_autoload_register(function (string \$entity) use(\$navigation) {
            \$path = \$navigation[\$entity];
            if (key_exists(\$entity, \$navigation)) {
                require __DIR__.'/'.\$path;
            }
        });
        AUTOLOAD_STRING;

        $this->phar->addFromString('autoload.php', $autoloadString);
    }

    protected function makeStub(): string
    {
        $executable = '';

        if (count($this->manifest->types) > 0) {
            $executable .= "require '{$this->navigationPrefix}/autoload.php';" . PHP_EOL;
        }
        if (key_exists('entrypoint', $this->config)) {
            [$class, $method] = $this->config['entrypoint'];
            $executable .= "{$class}::{$method}(\$argv);" . PHP_EOL;
        }

        return
            <<<STUB_CODE
        <?php
        Phar::mapPhar('{$this->buildName}');
        {$executable}
        __HALT_COMPILER();
        STUB_CODE;
    }
}