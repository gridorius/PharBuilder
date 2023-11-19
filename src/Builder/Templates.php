<?php

namespace Phnet\Builder;

use Phnet\Core\Resources;

class Templates
{
    public static function getIndex(string $pharName)
    {
        return <<<TEMPLATE_STRING
        <?php
        use Phnet\Core\Assembly;
        
        require '/var/lib/phnet/Phnet.Core.phar';
        Assembly::init(__DIR__, '{$pharName}');
        Assembly::entrypoint(\$argv);
        TEMPLATE_STRING;
    }

    public static function getTemplate(string $name){
        $notBuildPath = __DIR__.'/templates/'.$name;
        if(file_exists($notBuildPath))
            return file_get_contents($notBuildPath);
        else
            return Resources::get('templates/'.$name)->getContent();
    }
}