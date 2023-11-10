<?php

namespace Phnet\Builder;

use Exception;
use FilesystemIterator;
use RegexIterator;

class ProjectConfig
{
    public static function parseFromPath(string $configPath){
        return json_decode(file_get_contents($configPath), true);
    }

    public static function getConfig(string $folder)
    {
        return static::parseFromPath(static::findConfig($folder));
    }

    public static function findConfig(string $folder)
    {
        $iterator = new FilesystemIterator($folder);
        $regexpIterator = new RegexIterator($iterator, "/proj\.json$/", RegexIterator::MATCH);
        $procConfigs = [];

        foreach ($regexpIterator as $info) {
            $procConfigs[] = $regexpIterator->key();
        }

        if (count($procConfigs) == 0)
            throw new Exception('proj.json file not found');

        if (count($procConfigs) > 1)
            throw new Exception('Only 1 proj.json can exist');

        return $procConfigs[0];
    }
}