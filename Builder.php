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

    public function __construct($folder, $buildDirectory = null)
    {
        $this->folder = $folder;
        $this->config = $this->getConfig();

        $this->buildName = ($this->config['outName'] ?? $this->config['name'] ?? 'build') . '.phar';
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
        foreach ($this->config['depends'] as $path) {
            $subBuilder = new static($this->folder . DIRECTORY_SEPARATOR . $path, $this->buildDirectory);
            $subBuilder->build();
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
                $filename = pathinfo($sourcePath, PATHINFO_FILENAME);
                copy($sourcePath, $this->buildDirectory . DIRECTORY_SEPARATOR . $filename);
            }
        }

        if ($this->config['directories']) {
            foreach ($this->config['directories'] as $path) {
                $directoryIterator = new RecursiveIteratorIterator(
                    new RecursiveDirectoryIterator($this->folder . DIRECTORY_SEPARATOR . $path, FilesystemIterator::SKIP_DOTS)
                );
                $directoryIterator->rewind();
                while ($directoryIterator->valid()) {
                    $innerDirPath = $this->buildDirectory . DIRECTORY_SEPARATOR . $directoryIterator->getSubPath();
                    if (!is_dir($innerDirPath))
                        mkdir($innerDirPath, 0755, true);

                    copy($directoryIterator->key(), $this->buildDirectory . DIRECTORY_SEPARATOR . $directoryIterator->getSubPathName());
                    $directoryIterator->next();
                }
            }
        }
    }

    public function build()
    {
        $pattern = $this->config['pattern'] ?? "/\.php$/";

        $findIterator = new \RegexIterator(new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($this->folder, FilesystemIterator::SKIP_DOTS)
        ), $pattern, \RecursiveRegexIterator::GET_MATCH);
        $findIterator->rewind();

        $navigation = [];

        while ($findIterator->valid()) {
            $path = $findIterator->key();
            echo "File {$path} added;" . PHP_EOL;
            $innerPath = uniqid('include/include_') . '.php';
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
                        $navigation[$match['namespace'] . '\\' . $namespaceMatch['name']] = $innerPath;
                    }
                }
            } else {
                $rawContent .= $prepared;
            }

            $findIterator->next();
        }

        $this->phar->setStub($this->makeStub($navigation));
        $this->phar->stopBuffering();

        echo "Phar compiled to directory {$this->buildDirectory}" . PHP_EOL;
    }

    protected function makeStub($navigation)
    {
        $navigationString = var_export($navigation, true);
        $stub = '<?php' . PHP_EOL;
        $stub .= "Phar::mapPhar('{$this->buildName}');" . PHP_EOL;
        $stub .= "\$pharRoot = \"phar://{$this->buildName}\";" . PHP_EOL;
        $stub .= "\$navigation = {$navigationString};" . PHP_EOL;
        $stub .= "\$includedFiles = [];";

        $stub .= "spl_autoload_register(function (string \$entity) use (\$pharRoot, \$navigation, &\$includedFiles){
            \$path = \$pharRoot.'/'.\$navigation[\$entity];
            if(key_exists(\$entity, \$navigation) && empty(\$includedFiles[\$path])){
                require \$path;
                \$includedFiles[\$path] = true;
            }
        });" . PHP_EOL;

        $stub .=
            "\$includeIterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator(\$pharRoot.'/include', FilesystemIterator::SKIP_DOTS)
);
foreach(\$includeIterator as \$path){
    if(empty(\$includedFiles[\$path->getPathname()])){
        require \$path->getPathname();
        \$includedFiles[\$path->getPathname()] = true;
    }
}" . PHP_EOL;

        $stub .= $this->config['start'] . PHP_EOL;
        $stub .= "__HALT_COMPILER();" . PHP_EOL;

        return $stub;
    }
}