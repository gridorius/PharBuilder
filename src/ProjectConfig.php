<?php

namespace PharBuilder;

use FilesystemIterator;

class ProjectConfig
{
    public static function findConfig(string $folder){
        $iterator = new FilesystemIterator($folder);
        $regexpIterator = new \RegexIterator($iterator, "/proj\.json$/", \RegexIterator::MATCH);
        $procConfigs = [];

        foreach ($regexpIterator as $info) {
            $procConfigs[] = $regexpIterator->key();
        }

        if (count($procConfigs) == 0)
            throw new \Exception('proj.json file not found');

        if (count($procConfigs) > 1)
            throw new \Exception('Only 1 proj.json can exist');

        return $procConfigs[0];
    }

    public static function getConfig(string $folder){
        return json_decode(file_get_contents(static::findConfig($folder)), true);
    }
}