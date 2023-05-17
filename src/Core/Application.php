<?php

namespace Phnet\Core;

class Application
{
    protected $mainManifest;
    protected $manifests = [];
    protected $types = [];

    public function __construct(string $pharName)
    {
        $this->manifests[] = $this->mainManifest = json_decode(file_get_contents('phar://' . Assembly::getPath("{$pharName}/manifest.json")), true);
    }

    public static function run(string $pharName){
        $app = new static($pharName);
        $app->loadDepends();
        $app->registerResources();
        $app->mapTypes();
        $app->registerAutoload();
        $app->preloadTypes();
        $app->start();
    }

    protected function loadDepends()
    {
        $additional = $this->mainManifest['pharDepends'];

        while (count($additional) > 0) {
            $depend = array_shift($additional);
            $this->manifests[] = $dependManifest = json_decode(
                file_get_contents('phar://' . Assembly::getPath("{$depend['name']}.phar/manifest.json"))
                , true);
            $additional = array_merge($additional, $dependManifest['pharDepends']);
        }
    }

    protected function mapTypes()
    {
        foreach ($this->manifests as $manifest) {
            $pharDir = Assembly::getPath();
            $pharPath = "phar://{$pharDir}/{$manifest['name']}.phar";
            foreach ($manifest['types'] as $type => $path) {
                $this->types[$type] = "{$pharPath}/{$path}";
            }
        }
    }

    protected function registerResources(){
        foreach ($this->manifests as $manifest) {
            $pharDir = Assembly::getPath();
            $pharPath = "phar://{$pharDir}/{$manifest['name']}.phar";
            foreach ($manifest['resources'] as $name => $path){
                Resources::register($name, $pharPath.DIRECTORY_SEPARATOR.$path);
            }
        }
    }

    protected function registerAutoload()
    {
        spl_autoload_register(function (string $entity) {
            if (key_exists($entity, $this->types))
                require $this->types[$entity];
        }, false, true);
    }

    protected function preloadTypes(){
        foreach ($this->types as $type => $path) {
            class_exists($type);
        }
    }

    protected function start(){
        require Assembly::getPath("{$this->mainManifest['name']}.phar");
    }
}