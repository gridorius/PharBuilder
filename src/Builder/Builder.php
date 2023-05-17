<?php

namespace Phnet\Builder;

use Exception;
use Phar;
use Phnet\Builder\Tasks\TaskBase;
use SplFileInfo;

class Builder
{
    protected $name;
    protected $folder;
    protected $config;
    protected $buildDirectory;
    protected $buildName;
    /** @var Phar */
    protected $phar;
    protected $navigationPrefix;
    protected $manifest;
    protected $buildPipe = [];

    protected $isExecutable = false;
    protected $isIncluded = false;
    protected $_withPackages = true;

    public function __construct(string $configPath, $buildDirectory = null)
    {
        if (!is_file($configPath))
            throw new Exception("File {$configPath} not found");

        $this->manifest = new Manifest();
        $this->folder = (new SplFileInfo(dirname($configPath)))->getRealPath();
        $this->config = json_decode(file_get_contents($configPath), true);

        $this->name = $this->manifest->name = $this->config['name'];
        $this->manifest->version = $this->config['version'] ?? '0.0.0';

        $this->buildName = $this->config['name'] . '.phar';
        $this->navigationPrefix = "phar://{$this->buildName}";

        $this->buildDirectory = $buildDirectory;
    }

    public function withoutPackages()
    {
        $this->_withPackages = false;
        return $this;
    }

    public function executable()
    {
        $this->isExecutable = true;
        return $this;
    }

    public function getBuildName(): string
    {
        return $this->buildName;
    }

    public function getBuildDirectory()
    {
        return $this->buildDirectory;
    }

    public function buildProjectReferences(): self
    {
        if (!key_exists('projectReferences', $this->config))
            return $this;

        $this->buildPipe[] = function () {
            foreach ($this->config['projectReferences'] as $reference) {
                $subBuilder = new static($this->folder . DIRECTORY_SEPARATOR . $reference, $this->buildDirectory);
                if ($this->isExecutable || $this->isIncluded) {
                    $subBuilder->setParent($this->phar, $this->manifest);
                } else {
                    $this->manifest->pharDepends[] = new Depend($subBuilder->getName(), $subBuilder->getManifest()->version);
                }

                if ($this->_withPackages)
                    $subBuilder
                        ->buildPackageReferences();

                $subBuilder
                    ->buildProjectReferences()
                    ->buildFiles()
                    ->buildResources()
                    ->buildPhar();
            }
        };

        return $this;
    }

    public function setParent(Phar $phar, Manifest $manifest)
    {
        $this->phar = $phar;
        $this->manifest = $manifest;
        $this->isIncluded = true;
    }

    public function getName()
    {
        return $this->name;
    }

    public function getManifest(): Manifest
    {
        return $this->manifest;
    }

    public function buildPackageReferences()
    {
        if (!key_exists('packageReferences', $this->config))
            return $this;

        $this->buildPipe[] = function () {
            foreach ($this->config['packageReferences'] as $package => $version) {
                $this->manifest->pharDepends[] = new Depend($package, $version);
            }

            $localPackage = PackageManager::findLocally($package, $version);

            if (!$localPackage)
                throw new Exception("Package {$package} {$version} not found locally");

            PackageManager::unpackToBuild($localPackage['path'], $this->buildDirectory);
            echo "Package {$package} {$version} added to build" . PHP_EOL;
        };

        return $this;
    }

    public function buildPhar(): self
    {
        if (!$this->phar) {
            $this->createBuildDirectory();
            $this->createPharFile();
        }

        $pattern = $this->config['pattern'] ?? "\.php$";

        if (isset($this->config['buildFolders'])) {
            foreach ($this->config['buildFolders'] as $folder)
                $this->buildLibFolder($this->folder . DIRECTORY_SEPARATOR . $folder, "/{$pattern}/");
        } else {
            $this->buildLibFolder($this->folder, "/{$pattern}/");
        }

        foreach ($this->buildPipe as $build) {
            $build();
        }

        if (!$this->isIncluded)
            $this->createManifest();

        if ($this->isExecutable)
            $this->phar->addFromString("index.php", Templates::getExecutableAutoload($this->buildName));

        $this->phar->setStub($this->makeStub());

        if (!$this->isIncluded) {
            $this->phar->stopBuffering();
            echo "Phar {$this->buildName} compiled to directory {$this->buildDirectory}" . PHP_EOL;
        }

        $this->callTargets();

        return $this;
    }

    protected function createBuildDirectory()
    {
        if (!is_dir($this->buildDirectory)) {
            mkdir($this->buildDirectory, 0755, true);
        }

        $this->buildDirectory = (new SplFileInfo($this->buildDirectory))->getRealPath();
    }

    protected function createPharFile()
    {
        $pharPath = $this->buildDirectory . DIRECTORY_SEPARATOR . $this->buildName;

        if (file_exists($pharPath))
            unlink($pharPath);

        $this->phar = new Phar($pharPath);
        $this->phar->startBuffering();
    }

    protected function buildLibFolder($folder, $pattern)
    {
        echo "Start build folder: {$folder}" . PHP_EOL;
        $iterator = new ExcludeRegexDirectoryIteratorHandler($folder, $pattern, $this->config['exclude'] ?? null);
        $iterator->handle(function ($path, $subPath) {
            $this->buildLibFile($path, $subPath);
            $this->manifest->hashes[$subPath] = hash_file('sha256', $path);
        });

        echo "Folder builded: {$folder}" . PHP_EOL;
    }

    protected function buildLibFile($path, $innerPath)
    {
        $subPrefix = $this->isIncluded ? $this->name . '/' : '';
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

                    $innerPath = 'lib/' . $subPrefix . $innerPath;
                    $this->manifest->types[$entity] = $innerPath;

                    $this->phar->addFile($path, $innerPath);
                    echo "File {$path} added to library as {$innerPath};" . PHP_EOL;
                }
            }
        }
    }

    protected function createManifest()
    {
        $this->phar->addFromString('manifest.json', json_encode($this->manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    }

    protected function makeStub(): string
    {
        $executable = '';

        if (!empty($this->isExecutable)) {
            $executable .= "require '{$this->navigationPrefix}/index.php';";
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

    protected function callTargets()
    {
        if (!key_exists('targets', $this->config))
            return;

        $from = getcwd();
        chdir($this->folder);

        foreach ($this->config['targets'] as $target) {
            echo "Run target {$target}" . PHP_EOL;
            foreach ($target['tasks'] as $task) {
                $taskClass = "\Phnet\Tasks\\" . $task['type'];
                if (!class_exists($taskClass))
                    throw new Exception("Task {$task['type']} not found");

                /** @var TaskBase $taskObject */
                $taskObject = new $taskClass($task, [
                    'buildDirectory' => $this->buildDirectory
                ]);
                $taskObject->execute();
            }
        }
        chdir($from);
    }

    public function buildFiles()
    {
        if (!key_exists('files', $this->config))
            return $this;

        $this->buildPipe[] = function () {
            $resources = [];
            foreach ($this->config['files'] as $files) {
                $include = $files['include'];
                $exclude = $files['exclude'] ?? [];
                $buildDirectory = $this->buildDirectory;
                $exclude[] = "/\.proj\.json$/";
                if (PHP_OS == 'WINNT')
                    $buildDirectory = preg_replace("/\\\\/", "/", $buildDirectory);

                $exclude[] = "/" . preg_quote($buildDirectory, '/') . "/";

                try {
                    $iterator = new ExcludeRegexDirectoryIteratorHandler($this->folder, $include, $exclude);
                    $iterator->handle(function ($sourcePath, $subPath) use (&$resources) {
                        $target = $this->buildDirectory . DIRECTORY_SEPARATOR . $subPath;
                        $targetDir = dirname($target);
                        $resources[] = [$targetDir, $sourcePath, $subPath, $target];
                    });
                } catch (Exception $ex) {
                    echo "Directory {$this->folder} regex {$include}";
                    throw $ex;
                }
            }

            foreach ($resources as $resource) {
                [$targetDir, $sourcePath, $subPath, $target] = $resource;

                if (!is_dir($targetDir))
                    mkdir($targetDir, 0755, true);

                if (CONFIG_DEBUG) {
                    if (!file_exists($target))
                        if (!link($sourcePath, $target))
                            throw new Exception("Не удалось создать ссылку: {$sourcePath} > {$target}");
                } else {
                    if (!copy($sourcePath, $target))
                        throw new Exception("Не удалось копировать файл: {$sourcePath} > {$target}");
                }


                $this->manifest->files[] = $subPath;
                $this->manifest->hashes[$subPath] = hash_file('sha256', $sourcePath);

                echo "Copy file {$sourcePath} to {$target}" . PHP_EOL;
            }
        };

        return $this;
    }

    public function buildResources()
    {
        if (!key_exists('resources', $this->config))
            return $this;

        $this->buildPipe[] = function () {
            foreach ($this->config['resources'] as $resource) {
                $include = $resource['include'];
                $exclude = $resource['exclude'] ?? [];
                $buildDirectory = $this->buildDirectory;
                $exclude[] = "/\.proj\.json$/";
                if (PHP_OS == 'WINNT')
                    $buildDirectory = preg_replace("/\\\\/", "/", $buildDirectory);

                $exclude[] = "/" . preg_quote($buildDirectory, '/') . "/";

                try {
                    $iterator = new ExcludeRegexDirectoryIteratorHandler($this->folder, $include, $exclude);
                    $iterator->handle(function ($sourcePath, $subPath) use (&$resources) {
                        $target = 'resources/' . $subPath;
                        $this->phar->addFile($sourcePath, $target);
                        $this->manifest->resources[$subPath] = $target;
                        $this->manifest->hashes[$subPath] = hash_file('sha256', $sourcePath);
                        echo "Resource file {$sourcePath} added" . PHP_EOL;
                    });
                } catch (Exception $ex) {
                    echo "Directory {$this->folder} regex {$include}";
                    throw $ex;
                }
            }
        };

        return $this;
    }
}