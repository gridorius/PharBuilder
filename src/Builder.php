<?php

namespace PharBuilder;

use FilesystemIterator;
use Phar;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

class Builder
{
    protected $folder;
    protected $config;
    protected $buildDirectory;
    protected $buildName;
    protected $phar;
    protected $depends = [];
    protected $navigationPrefix;
    protected $navigation = [];

    public function __construct($folder, $buildDirectory = null)
    {
        $this->folder = $folder;
        $this->config = $this->getConfig();

        $this->buildName = ($this->config['outName'] ?? $this->config['name'] ?? 'build') . '.phar';
        $this->navigationPrefix = "phar://{$this->buildName}";

        if ($buildDirectory) {
            $this->buildDirectory = $buildDirectory;
        } else if ($this->config['outDir']) {
            $this->buildDirectory = $this->folder . DIRECTORY_SEPARATOR . $this->config['outDir'];
        } else {
            $this->buildDirectory = $this->folder . '/build';
        }

        $this->createPhar();
        $this->buildDepends();
        $this->loadIncludes();
    }

    protected function getConfig()
    {
        return json_decode(file_get_contents($this->folder . '/build.config.json'), true);
    }

    protected function buildDepends()
    {
        foreach ($this->config['depends'] as $depend) {
            if (filter_var($depend, FILTER_VALIDATE_URL)) {
                `git clone $depend /tmp/temb_build`;
                $subBuilder = new static('/tmp/temb_build', $this->buildDirectory);
                $subBuilder->build();
                unlink('/tmp/temb_build');
            } else {
                $path = $depend;
                $subBuilder = new static($this->folder . DIRECTORY_SEPARATOR . $path, $this->buildDirectory);
                $subBuilder->build();
            }

            $this->depends[] = $subBuilder->getBuildName();
        }
    }

    protected function createPhar()
    {
        if (!is_dir($this->buildDirectory)) {
            mkdir($this->buildDirectory, 0755, true);
        }

        $pharPath = $this->buildDirectory . DIRECTORY_SEPARATOR . $this->buildName;

        $this->phar = new Phar($pharPath, 0, $this->buildName);
        $this->phar->startBuffering();
    }

    public function getBuildName()
    {
        return $this->buildName;
    }

    protected function loadIncludes()
    {
        if ($this->config['files']) {
            foreach ($this->config['files'] as $path) {
                $sourcePath = $this->folder . DIRECTORY_SEPARATOR . $path;
                $filename = pathinfo($sourcePath, PATHINFO_BASENAME);
                copy($sourcePath, $this->buildDirectory . DIRECTORY_SEPARATOR . $filename);
            }
        }

        if ($this->config['directories']) {
            foreach ($this->config['directories'] as $path) {
                $target = '';
                if (is_array($path)) {
                    $target = $path['target'] . DIRECTORY_SEPARATOR;
                    $path = $path['source'];
                }
                $directoryIterator = new RecursiveIteratorIterator(
                    new RecursiveDirectoryIterator($this->folder . DIRECTORY_SEPARATOR . $path, FilesystemIterator::SKIP_DOTS)
                );
                $directoryIterator->rewind();
                while ($directoryIterator->valid()) {
                    $innerDirPath = $this->buildDirectory . DIRECTORY_SEPARATOR . $target . $directoryIterator->getSubPath();
                    if (!is_dir($innerDirPath))
                        mkdir($innerDirPath, 0755, true);

                    copy($directoryIterator->key(), $this->buildDirectory . DIRECTORY_SEPARATOR . $target . $directoryIterator->getSubPathName());
                    $directoryIterator->next();
                }
            }
        }
    }

    public function build()
    {
        $pattern = $this->config['pattern'] ?? "/\.php$/";
        $navigation = [];

        if ($this->config['buildFolders']) {
            foreach ($this->config['buildFolders'] as $folder)
                $this->buildFolder($folder, $pattern, $navigation);
        } else {
            $this->buildFolder($this->folder, $pattern, $navigation);
        }

        $this->buildFile(__DIR__ . '/Assembly.php', 'Assembly.php');

        if ($this->config['bootFile']) {
            $this->buildFile($this->folder . DIRECTORY_SEPARATOR . $this->config['bootFile'], 'boot.php');
        } else {
            $this->phar->addFromString('boot.php', '<?php' . PHP_EOL . ($this->config['bootScript'] ?? ''));
        }

        $this->phar->setStub($this->makeStub());
        $this->phar->stopBuffering();

        echo "Phar compiled to directory {$this->buildDirectory}" . PHP_EOL;
    }

    protected function buildFolder($folder, $pattern, &$navigation)
    {
        echo "Start build folder: {$folder}".PHP_EOL;
        $findIterator = new \RegexIterator(new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($folder, FilesystemIterator::SKIP_DOTS)
        ), $pattern, \RecursiveRegexIterator::GET_MATCH);
        $findIterator->rewind();

        while ($findIterator->valid()) {
            $path = $findIterator->key();
            $innerPath = 'include/' . $findIterator->getSubPathName();
            $this->phar->addFile($path, $innerPath);

            $this->buildFile($path, $innerPath);

            $findIterator->next();
        }

        echo "Folder builded: {$folder}".PHP_EOL;
    }

    protected function buildFile($path, $innerPath)
    {
        $this->phar->addFile($path, $innerPath);

        $content = php_strip_whitespace($path);
        $prepared = preg_replace("/^<\?(php)?/", '', $content);
        if (preg_match_all(
            "/((namespace\s(?<namespace>[\w1-9_\\\\]+?))((;(?<content_t1>.+?))|(\{(?<content_t2>((?>[^{}]+)|(?7))*)\})))(?=\s*(?1)|\z)/s",
            $prepared,
            $matches,
            PREG_SET_ORDER
        )) {
            foreach ($matches as $match) {
                $namespaceContent = $match['content_t1'] ?? $match['content_t2'] ?? '';
                preg_match_all("/(class|interface|trait)\s+(?<name>[\w1-9_\\\\]+)/", $namespaceContent, $namespaceMatches, PREG_SET_ORDER);

                foreach ($namespaceMatches as $namespaceMatch) {
                    $this->navigation[$match['namespace'] . '\\' . $namespaceMatch['name']] =
                        $this->navigationPrefix . '/' . $innerPath;
                }
            }
        }
        echo "File {$path} added;" . PHP_EOL;
    }

    protected function makeStub(): string
    {
        $navigationString = var_export($this->navigation, true);
        $dependsString = var_export($this->depends, true);
        $stub = <<<STUB_CODE
<?php
Phar::mapPhar('{$this->buildName}');
if(!class_exists(PharBuilder\Assembly::class))
    require '{$this->navigationPrefix}/Assembly.php';
 
PharBuilder\Assembly::registerPhar(
    '{$this->buildName}',
    $dependsString,
    $navigationString
);

PharBuilder\Assembly::startListenAutoload();

require '{$this->navigationPrefix}/boot.php';
__HALT_COMPILER();
STUB_CODE;

        return $stub;
    }
}