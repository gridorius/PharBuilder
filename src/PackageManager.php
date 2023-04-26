<?php

namespace PharBuilder;

class PackageManager
{
    protected $sources;
    protected static $packagesPath = '/var/phnet/packages/';

    public function __construct(array $sources)
    {
        $this->sources = $sources;
    }

    public function findPackage($name, $version)
    {
        $query = http_build_query([
            'name' => $name,
            'version' => $version
        ]);

        echo "Find package {$name} {$version} in soruces: " . implode(', ', $this->sources) . PHP_EOL;

        foreach ($this->sources as $source) {
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $source . '/find/package' . '?' . $query);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            $response = curl_exec($ch);

            if ($error = curl_error($ch))
                throw new \Exception($error);

            curl_close($ch);
            $found = json_decode($response, true);
            if (is_null($found))
                throw new \Exception('decode exception');

            if (empty($found))
                continue;

            $package = reset($found);
            return [
                'source' => $source,
                'path' => $source . "/package/{$package['PackageId']}/",
                'version' => $package['PackageVersion']
            ];
        }

        return null;
    }

    public static function findLocally($name, $version): ?string
    {
        $packagePath = static::$packagesPath . $name . '/' . $version . '.phar';
        $found = glob($packagePath);
        if (count($found) > 0)
            return reset($found);

        return null;
    }

    public static function uploadPackage(
        string $packageName,
        string $packageVersion,
        string $packageFile,
        string $source,
        string $password,
        bool $isPublic = true
    )
    {
        $post = [
            'package' => curl_file_create($packageFile, ''),
            'name' => $packageName,
            'version' => $packageVersion,
            'password' => $password,
            'isPublic' => $isPublic
        ];

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $source . '/add/package');
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $post);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $response = curl_exec($ch);

        if ($error = curl_error($ch))
            throw new \Exception($error);

        curl_close($ch);

        $result = json_decode($response, true);
        if (is_null($result))
            throw new \Exception('Result not converted');

        if($error = $result['error'])
            throw new \Exception($error);

        return $result['PackageId'];
    }

    public static function unpackToBuild(string $packagePath, string $buildPath)
    {
        $data = new \Phar($packagePath);
        $data->extractTo($buildPath);
    }

    public static function getPackageData($path)
    {
        return file_get_contents($path);
    }

    public static function savePackage($name, $version, $data): string
    {
        $packagePath = static::$packagesPath . $name;
        mkdir($packagePath, 0755, true);
        file_put_contents($packagePath . '/' . $version . '.phar', $data);

        return $packagePath . '/' . $version . '.phar';
    }
}