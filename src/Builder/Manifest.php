<?php

namespace Phnet\Builder;

class Manifest
{
    public $name = '';
    public $version = '';
    public $types = [];
    public $include = [];
    public $files = [];
    public $resources = [];
    public $hashes = [];
    public $pharDepends = [];
    public $entrypoint;

    public function __construct(array $config)
    {
        if(empty($config['name']))
            throw new \Exception('Project name not set');

        $this->name = $config['name'];
        $this->version = $config['version'] ?? '1.0.0';
        $this->pharDepends = $this->config['packageReferences'] ?? [];
        if(!empty($config['entrypoint']))
            $this->entrypoint = $config['entrypoint'];
    }

    public static function getFrom(string $path){
        return json_decode(file_get_contents("phar://{$path}/manifest.json"), false);
    }
}