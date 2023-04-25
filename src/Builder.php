<?php

namespace PharBuilder;

use FilesystemIterator;
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

    public function __construct($folder, $buildDirectory = null)
    {
        $this->manifest = new Manifest();
        $this->folder = $folder;
        $this->config = $this->getProjectConfig();

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

    public function getConfig()
    {
        return $this->config;
    }

    protected function getProjectConfig()
    {
        $iterator = new FilesystemIterator($this->folder);
        $regexpIterator = new \RegexIterator($iterator, "/proj\.json$/", \RegexIterator::MATCH);
        $procConfigs = [];

        foreach ($regexpIterator as $info) {
            $procConfigs[] = $regexpIterator->key();
        }

        if (count($procConfigs) == 0)
            throw new \Exception('proj.json file not found');

        if (count($procConfigs) > 1)
            throw new \Exception('Only 1 proj.json can exist');

        return json_decode(file_get_contents($procConfigs[0]), true);
    }

    protected function createPharFile()
    {
        if (!is_dir($this->buildDirectory)) {
            mkdir($this->buildDirectory, 0755, true);
        }

        $pharPath = $this->buildDirectory . DIRECTORY_SEPARATOR . $this->buildName;

        if (file_exists($pharPath))
            unlink($pharPath);

        $this->phar = new Phar($pharPath);
        $this->phar->startBuffering();
    }

    public function buildProjectReferences(): self
    {
        if (!key_exists('projectReferences', $this->config))
            return $this;

        $this->buildPipe[] = function () {
            foreach ($this->config['projectReferences'] as $reference) {
                $subBuilder = new static($this->folder . DIRECTORY_SEPARATOR . dirname($reference), $this->buildDirectory);
                $subBuilder->buildPhar();
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

            // todo: Реализовать
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
                $exclude = $resourceConfiguration['exclude'];

                $iterator = new \RegexIterator(
                    new RecursiveIteratorIterator(
                        new \RecursiveDirectoryIterator($this->folder, FilesystemIterator::SKIP_DOTS)
                    ), $include
                );
                foreach ($iterator as $fileInfo) {
                    $sourcePath = $iterator->key();
                    foreach ($exclude as $ex)
                        if (preg_match($ex, $sourcePath))
                            continue 2;

                    $innerPath = str_replace($this->folder, '', $sourcePath);
                    $target = $this->buildDirectory . $innerPath;
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
        $this->createPharFile();

        $pattern = $this->config['pattern'] ?? "\.php$";

        if ($this->config['buildFolders']) {
            foreach ($this->config['buildFolders'] as $folder)
                $this->buildLibFolder($this->folder . DIRECTORY_SEPARATOR . $folder, "/{$pattern}/");
        } else {
            $this->buildLibFolder($this->folder, "/{$pattern}/");
        }

        foreach ($this->buildPipe as $build){
            $build();
        }

        $this->buildLibFile(__DIR__ . '/Assembly.php', 'Assembly.php');
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

        while ($findIterator->valid()) {
            $path = $findIterator->key();
            if ($this->config['exclude']) {
                foreach ($this->config['exclude'] as $pattern) {
                    if (preg_match($pattern, $path)) {
                        $findIterator->next();
                        continue 2;
                    }

                    $innerPath = $findIterator->getSubPathName();
                    $this->buildLibFile($path, $innerPath);
                }
            } else {
                $innerPath = $findIterator->getSubPathName();
                $this->buildLibFile($path, $innerPath);
            }
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
        if ($this->config['entrypoint']) {
            [$class, $method] = $this->config['entrypoint'];
            $executable = "{$class}::{$method}(\$argv)";
        }

        return
            <<<STUB_CODE
        <?php
        Phar::mapPhar('{$this->buildName}');
        require '{$this->navigationPrefix}/autoload.php';
        {$executable};
        __HALT_COMPILER();
        STUB_CODE;
    }
}