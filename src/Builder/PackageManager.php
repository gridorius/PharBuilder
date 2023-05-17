<?php

namespace Phnet\Builder;

use Closure;
use Exception;
use Phar;

class PackageManager
{
    protected static $packagesPath = '/var/Phnett/packages/';
    protected $sources;

    public function __construct(array $sources)
    {
        $this->sources = $sources;
    }

    public static function uploadPackage(
        string $packageName,
        string $packageVersion,
        string $packageFile,
        string $source,
        string $password,
        bool   $isPublic = true,
        array  $references = []
    )
    {
        $post = [
            'package' => curl_file_create($packageFile, ''),
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

    public static function unpackToBuild(string $packagePath, string $buildPath)
    {
        $data = new Phar($packagePath);
        $data->extractTo($buildPath);
    }

    public function loadDepends($depends)
    {
        foreach ($depends as $name => $version) {
            $localPackage = static::findLocally($name, $version);
            $subDepends = [];
            if ($localPackage) {
                echo "Package {$name}, version {$localPackage['version']} found locally" . PHP_EOL;
                $localPath = $localPackage['path'];

                $manifestPath = "phar://{$localPath}/package.manifest.json";

                if ($manifestRaw = file_get_contents($manifestPath)) {
                    $manifest = json_decode($manifestRaw, true);
                    $subDepends = $manifest['packageReferences'];
                } else {
                    throw new Exception("Failed read package manifest in {$manifestPath}");
                }
            } else {
                $foundPackage = $this->findPackage($name, $version);
                if ($foundPackage) {
                    $localPath = static::getPackagePath($name, $foundPackage['version']);

                    $subDepends = $foundPackage['references'];

                    echo PHP_EOL;
                    static::downloadPackage($foundPackage['path'], $localPath, function ($full, $loaded) use ($name, $foundPackage) {
                        $piece = round(($loaded / $full) * 20);
                        $progress = str_pad(str_pad('', $piece, '+'), 20, '-');
                        echo "Download package {$name}, version {$foundPackage['version']}: [$progress]\r";
                    });
                    echo PHP_EOL;
                }
            }

            if (!$localPath)
                throw new Exception('package not found');

            $this->loadDepends($subDepends);
        }
    }

    public static function findLocally($name, $version): ?array
    {
        $packagePath = static::$packagesPath . $name . '/' . $version . '.phar';
        $found = glob($packagePath);
        if (count($found) > 0) {
            $path = reset($found);
            preg_match("/\/(?<version>([^\/]+))\.phar$/", $path, $matches);
            return [
                'version' => $matches['version'],
                'path' => $path
            ];
        }

        return null;
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
                throw new Exception($error);

            curl_close($ch);
            $found = json_decode($response, true);
            if (is_null($found))
                throw new Exception('decode exception');

            if (empty($found))
                continue;

            $package = reset($found);
            return [
                'source' => $source,
                'path' => $source . "/package/{$package['PackageId']}/",
                'version' => $package['PackageVersion'],
                'references' => $package['PackageReferences']
            ];
        }

        return null;
    }

    public static function getPackagePath($name, $version): string
    {
        $packagePath = static::$packagesPath . $name;
        mkdir($packagePath, 0755, true);
        return $packagePath . '/' . $version . '.phar';
    }

    public static function downloadPackage($sourcePath, $targetPath, Closure $onIteration = null)
    {
        $context = stream_context_create(array(
            'http' => array(
                'timeout' => 2.0
            )
        ));

        $from = fopen($sourcePath, 'r', false, $context);
        $to = fopen($targetPath, 'w');
        $loaded = 0;

        $headers = [];
        foreach (stream_get_meta_data($from)['wrapper_data'] as $item) {
            [$header, $value] = explode(':', $item, 2);
            $headers[$header] = $value;
        }

        $size = $headers['Content-Length'];

        while (!feof($from)) {
            $data = fgets($from, 10000);
            $loaded += strlen($data);
            $onIteration($size, $loaded);
            fwrite($to, $data);
            if ($loaded == $size)
                break;
        }

        fclose($from);
        fclose($to);
    }
}