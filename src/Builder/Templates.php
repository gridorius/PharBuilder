<?php

namespace Phnet\Builder;

class Templates
{
    public static function getIndex(string $pharName)
    {

        return <<<TEMPLATE_STRING
<?php
\$innerPhar = '{$pharName}';
\$coreManifest = json_decode(file_get_contents('phar://'.__DIR__.'/Phnet.Core.phar/manifest.json'), true);
spl_autoload_register(function(string \$entity) use(\$coreManifest){
    if(key_exists(\$entity, \$coreManifest['types']))
        require 'phar://'.__DIR__.'/Phnet.Core.phar/'.\$coreManifest['types'][\$entity];
});

\Phnet\Core\Application::run(\$innerPhar);
TEMPLATE_STRING;
    }

    public static function getExecutableAutoload(string $pharName)
    {
        return <<<TEMPLATE_STRING
<?php

\$innerPhar = '{$pharName}';
\$innerPharPath = "phar://{\$innerPhar}";
\$manifest = \$mainManifest = json_decode(file_get_contents("phar://{\$innerPhar}/manifest.json"), true);

\$types = [];
foreach (\$manifest['types'] as \$type => \$path) {
    \$types[\$type] = "{\$innerPharPath}/{\$path}";
}

spl_autoload_register(function (string \$entity) use (\$types) {
    if (key_exists(\$entity, \$types))
        require \$types[\$entity];
}, false, true);

foreach (\$types as \$type) {
    class_exists(\$type);
}
TEMPLATE_STRING;
    }
}