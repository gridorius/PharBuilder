<?php

namespace Phnet\Builder\Manifests;

class ProjectManifest
{
    public $type = 'project';
    public $name = '';
    public $version = '';
    public $types = [];
    public $include = [];
    public $files = [];
    public $resources = [];
    public $depends = [];
    public $entrypoint;

    public function __construct(array $config)
    {
        if(empty($config['name']))
            throw new \Exception('Project name not set');

        $this->name = $config['name'];
        $this->version = $config['version'] ?? '1.0.0';
        $this->depends = $this->config['packageReferences'] ?? [];
        if(!empty($config['entrypoint']))
            $this->entrypoint = $config['entrypoint'];
    }

    public static function getFrom(string $path){
        return json_decode(file_get_contents("phar://{$path}/manifest.json"), false);
    }
}