<?php

namespace Phnet\Builder;

use Exception;
use Phnet\Builder\Console\MultilineOutput;
use Phnet\Builder\Console\PercentEcho;
use Phnet\Builder\ExternalPackages\IExternalPackage;
use Phnet\Builder\FIleScanning\Scanner;
use Phnet\Builder\HttpClient\Client;
use Phnet\Builder\Manifests\PackageManifest;
use Phnet\Builder\Manifests\ProjectManifest;
use Phnet\Builder\Source\Source;
use Phnet\Builder\Source\SourcePackage;

class PackageManager
{
    protected string $packagesPath;
    /** @var IExternalPackage[] $external */
    protected array $external = [];

    public function __construct(array $config)
    {
        $this->packagesPath = $config['packagePath'];
        if (!is_dir($this->packagesPath))
            mkdir($this->packagesPath, 0755, true);

        foreach (get_declared_classes() as $class) {
            if (is_subclass_of($class, IExternalPackage::class)) {
                $external = new $class($this);
                $this->external[$external->getName()] = $external;
            }
        }
    }

    public function uploadPackage(
        Source $source,
        array  $packResult,
        bool   $isPublic = true
    )
    {
        $data = [
            'package' => curl_file_create($packResult['path']),
            'name' => $packResult['name'],
            'version' => $packResult['version'],
            'isPublic' => $isPublic,
            'references' => $packResult['depends']
        ];

        return $source->uploadPackage($data);
    }

    public function loadDepends($depends, array $sources): PackageManager
    {
        if (empty($depends)) return $this;
        $needDownload = [];
        $subDepends = [];
        foreach ($depends as $name => $version) {
            $localPackage = $this->findLocal($name, $version);
            if ($localPackage) {
                MultilineOutput::getInstance()
                    ->getRow('depends')
                    ->addSubdata("Package {$name}, version {$localPackage['version']} found locally");

                $localPath = $localPackage['path'];
                $manifest = require $localPath;
                foreach ($manifest['depends'] as $depend => $ver)
                    $subDepends[$depend] = $ver;
            } else {
                $needDownload[$name] = $version;
            }
        }

        $foundPackages = $this->findPackages($needDownload, $sources);
        foreach ($foundPackages as $path => $sourcePackage) {
            $sourcePackage->download($path);
            $manifest = require $path;
            foreach ($manifest['depends'] as $depend => $ver)
                $subDepends[$depend] = $ver;
        }

        $this->loadDepends($subDepends, $sources);
        return $this;
    }

    public function loadExternalDepends(array $references): PackageManager
    {
        foreach ($references as $reference) {
            if ($this->hasExternal($reference['type'])) {
                $this->getExternal($reference['type'])->restore($reference);
            } else {
                throw new Exception("Restore handler for type {$reference['type']} not found");
            }
        }

        return $this;
    }

    public function hasExternal(string $name): bool
    {
        return !empty($this->external[$name]);
    }

    public function getExternal(string $name): IExternalPackage
    {
        return $this->external[$name];
    }

    public function findLocal(string $name, string $version): ?array
    {
        $found = [];
        foreach (glob($this->getPackagePath($name, $version)) as $packagePath) {
            $found[] = $packagePath;
        }

        if (count($found) == 0)
            return null;

        $path = end($found);
        preg_match("/\/(?<version>([^\/]+))\.phar$/", $path, $matches);
        return [
            'version' => $matches['version'],
            'path' => $path
        ];
    }

    public function pack(string $path, ProjectManifest $manifest, array $depends, array $externalDepends = [], array $requiredModules = []): array
    {
        $packagePath = $this->getPackagePath($manifest->name, $manifest->version);
        $phar = new \Phar($packagePath);
        $phar->startBuffering();
        $phar->buildFromDirectory($path);
        $packageManifest = new PackageManifest($manifest->name, $manifest->version, $depends);
        $packageManifest->externalDepends = $externalDepends;
        $packageManifest->requiredModules = $requiredModules;
        $files = Scanner::scanFiles($path)->getFiles();
        foreach ($files as $filePath)
            $packageManifest->hashes[$filePath] = hash_file('sha256', $path . DIRECTORY_SEPARATOR . $filePath);

        $phar->setMetadata($packageManifest);
        $phar->stopBuffering();

        return [
            'path' => $packagePath,
            'name' => $packageManifest->name,
            'version' => $packageManifest->version,
            'depends' => $depends
        ];
    }

    public function unpack(string $path, string $outDir)
    {
        $data = new \Phar($path);
        $data->extractTo($outDir, null, true);
    }

    private function getPackagePath(string $name, string $version): string
    {
        return $this->packagesPath . $name . '_' . $version . '.phar';
    }

    /**
     * @param Source[] $sources
     * @return SourcePackage[]
     */
    public function findPackages(array $depends, array $sources): array
    {
        $result = [];
        $notFound = $depends;
        foreach ($sources as $source) {
            $result = $source->findPackages($notFound);
            foreach ($result['found'] as $name => $version) {
                unset($notFound[$name]);
                $result[$this->getPackagePath($name, $version)] = new SourcePackage($source, $name, $version);
            }
        }

        if (count($notFound) > 0) {
            $packages = [];
            foreach ($notFound as $name => $version)
                $packages[] = "{$name}({$version})";
            $packagesString = implode(', ', $packages);
            throw new Exception("Not found packages: {$packagesString}");
        }

        return $result;
    }
}