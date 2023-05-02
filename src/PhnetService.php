<?php

namespace PharBuilder;

use SplFileInfo;

class PhnetService
{
    public function makeIndexFile(string $directory, string $phar){
        $indexPath = (new SplFileInfo($directory))->getRealPath();
        $pharPath = $indexPath . '/' . $phar;

        if (!file_exists($pharPath))
            throw new \Exception("{$pharPath} not found");

        file_put_contents($indexPath . '/index.php', Templates::getIndex($phar));
    }
}