<?php

namespace Phnet\Builder;

use Exception;
use SplFileInfo;

class PhnetService
{
    public function makeIndexFile(string $directory, string $name)
    {
        $indexPath = (new SplFileInfo($directory))->getRealPath();
        $pharPath = $indexPath . '/' . $name.'.phar';

        if (!file_exists($pharPath))
            throw new Exception("{$pharPath} not found");

        file_put_contents($indexPath . '/index.php', Templates::getIndex($name));
    }
}