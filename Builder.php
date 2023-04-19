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

        $findIterator = new \RegexIterator(new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($this->folder, FilesystemIterator::SKIP_DOTS)
        ), $pattern, \RecursiveRegexIterator::GET_MATCH);
        $findIterator->rewind();

        $navigation = [];
        $navigationPrefix = "phar://{$this->buildName}";

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
                        $navigation[$match['namespace'] . '\\' . $namespaceMatch['name']] =
                            $navigationPrefix . '/' . $innerPath;
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
        $dependsString = var_export($this->depends, true);
        $stub = '<?php' . PHP_EOL;
        $stub .= "Phar::mapPhar('{$this->buildName}');" . PHP_EOL;
        $stub .= "\$navigation = {$navigationString};";
        $stub .= "\$GLOBALS['__ASSEMBLY_NAVIGATION'] = empty(\$GLOBALS['__ASSEMBLY_NAVIGATION']) ? \$navigation : array_merge(\$GLOBALS['__ASSEMBLY_NAVIGATION'], \$navigation);" . PHP_EOL;
        $stub .= "\$GLOBALS['__INCLUDED_FILES'] = \$GLOBALS['__INCLUDED_FILES'] ?? [];" . PHP_EOL;
        $stub .= "\$GLOBALS['__INCLUDED_ASSEMBLIES'] = \$GLOBALS['__INCLUDED_ASSEMBLIES'] ?? [];" . PHP_EOL;
        $stub .= "\$GLOBALS['__INCLUDED_ASSEMBLIES']['{$this->buildName}']['name'] = '{$this->buildName}';" . PHP_EOL;
        $stub .= "\$GLOBALS['__INCLUDED_ASSEMBLIES']['{$this->buildName}']['depends'] = {$dependsString};" . PHP_EOL;
        $stub .= "\$GLOBALS['__INCLUDED_ASSEMBLIES']['{$this->buildName}']['include'] = function(){
            \$includeIterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator('phar://{$this->buildName}/include', FilesystemIterator::SKIP_DOTS)
            );
            foreach(\$includeIterator as \$path){
                if(empty(\$GLOBALS['__INCLUDED_FILES'][\$path->getPathname()])){
                    require \$path->getPathname();
                    \$GLOBALS['__INCLUDED_FILES'][\$path->getPathname()] = true;
                }
            }
        };" . PHP_EOL;

        $stub .= "spl_autoload_register(function (string \$entity){
            \$path = \$GLOBALS['__ASSEMBLY_NAVIGATION'][\$entity];
            if(key_exists(\$entity, \$GLOBALS['__ASSEMBLY_NAVIGATION']) && empty(\$GLOBALS['__INCLUDED_FILES'][\$path])){
                require \$path;
                \$GLOBALS['__INCLUDED_FILES'][\$path] = true;
            }
        });" . PHP_EOL;

        $stub .= $this->config['start'] . PHP_EOL;
        $stub .= "__HALT_COMPILER();" . PHP_EOL;

        return $stub;
    }
}