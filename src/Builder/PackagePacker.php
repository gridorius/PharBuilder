<?php

namespace Phnet\Builder;

use Exception;

class PackagePacker
{
    protected static $packagesPath = '/var/Phnett/packages/';

    public static function pack(string $path, array $config){
        if(!is_dir(static::$packagesPath))
            mkdir(static::$packagesPath, 0755, true);

        $packagePath = static::getPackagePath($config['name'], $config['version']);
        $phar = new \Phar($packagePath);
        $phar->startBuffering();
        $phar->buildFromDirectory($path);

        $packageManifest = static::createPackageManifest($config);

        $phar->addFromString('package.manifest.json', json_encode($packageManifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        $phar->stopBuffering();
    }

    public static function uploadPackage(
        string $packageName,
        string $packageVersion,
        string $source,
        string $password,
        bool   $isPublic = true,
        array  $references = []
    )
    {
        $path = self::getPackagePath($packageName, $packageVersion);
        if(!is_file($path))
            throw new \Exception("Package {$packageName} version {$packageVersion} not found locally");

        $post = [
            'package' => curl_file_create($path, ''),
            'name' => $packageName,
            'version' => $packageVersion,
            'password' => $password,
            'isPublic' => $isPublic,
            'references' => $references
        ];

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $source . '/add/package');
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $post);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $response = curl_exec($ch);

        if ($error = curl_error($ch))
            throw new Exception($error);

        curl_close($ch);

        $result = json_decode($response, true);
        if (is_null($result))
            throw new Exception('Result not converted');

        if (!empty($result['error']))
            throw new Exception($result['error']);

        return $result['PackageId'];
    }

    public static function findLocal(string $package, string $version): ?string{
        $found = [];
        foreach (glob(static::getPackagePath($package, $version)) as $packagePath){
            $found[] = $packagePath;
        }

        if(count($found) == 0)
            return null;

        return $found[count($found) - 1];
    }

    public static function unpack(string $path, string $outDir){
        $data = new \Phar($path);
        $data->extractTo($outDir);
    }

    private static function getPackagePath(string $name, string $version): string{
        return static::$packagesPath.$name.'_'.$version.'.phar';
    }

    private static function createPackageManifest(array $config): array{
        return [
            'name' => $config['name'],
            'version' => $config['version'],
            'packageReferences' => $packageConfig['packageReferences'] ?? []
        ];
    }
}