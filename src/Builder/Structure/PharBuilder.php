<?php

namespace Phnet\Builder\Structure;

use Exception;
use Phar;
use Phnet\Builder\Manifests\SingleManifest;
use Phnet\Builder\Templates;
use Phnet\Core\Resources;

class PharBuilder extends PharBuilderBase
{
    public function build(string $outDir): void
    {
        $projectPhar = $this->createPhar($outDir);
        $this->buildBase($outDir, $projectPhar);

        if ($this->structure->stub)
            $projectPhar->setStub(file_get_contents($this->structure->stub));
        else {
            $projectPhar->setStub(Templates::getTemplate('stub.php'));
        }

        $projectPhar->stopBuffering();
    }

    public function buildLibrary(string $outDir)
    {
        $projectPhar = $this->createPhar($outDir);
        $this->buildBase($outDir, $projectPhar);
        $projectPhar->setStub(Templates::getTemplate('loader.stub.php'));
        $projectPhar->stopBuffering();
    }

    protected function buildBase(string $outDir, Phar $projectPhar)
    {
        $this->copyFiles($outDir);
        $this->structure->setResourcePrefix(Paths::RESOURCES);
        $this->structure->setTypePrefix(Paths::TYPES);
        $this->structure->setIncludePrefix(Paths::PHP_INCLUDE);
        $projectPhar->startBuffering();
        $projectPhar->buildFromIterator($this->getFileIterator());
        $this->createManifest($projectPhar);
    }

    public function buildSingleProject(string $outDir, Phar $projectPhar): void
    {
        $this->copyFiles($outDir);
        $projectPhar->startBuffering();
        $this->structure->setResourcePrefix(Paths::RESOURCES . $this->structure->manifest->name . DIRECTORY_SEPARATOR);
        $this->structure->setTypePrefix(Paths::TYPES . $this->structure->manifest->name . DIRECTORY_SEPARATOR);
        $this->structure->setIncludePrefix(Paths::PHP_INCLUDE . $this->structure->manifest->name . DIRECTORY_SEPARATOR);
        $projectPhar->buildFromIterator($this->getFileIterator());
        $projectPhar->stopBuffering();
    }

    public static function buildSingle(Phar $phar, SingleManifest $manifest)
    {
        $phar->startBuffering();
        $phar->setStub(Templates::getTemplate('single.stub.php'));
        $phar->addFromString(
            'manifest.json',
            json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
        );
        $phar->stopBuffering();
    }
}