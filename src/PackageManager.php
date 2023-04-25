<?php

namespace PharBuilder;

class PackageManager
{
    protected $source;

    public function __construct(array $configuration)
    {
        $this->source = $configuration['source'];
    }

    public function uploadPackage(string $packageName, string $packageVersion, string $packageFile)
    {
        $query = http_build_query([
            'name' => $packageName,
            'version' => $packageVersion
        ]);

        $post = [
            'package' => curl_file_create($packageFile)
        ];

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $this->source . '?' . $query);
        curl_setopt($ch, CURLOPT_POST,1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $post);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $response = curl_exec($ch);
        curl_close($ch);

        var_dump($response);
    }
}