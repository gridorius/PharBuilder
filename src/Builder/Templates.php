<?php

namespace Phnet\Builder;

class Templates
{
    public static function getIndex(string $pharName)
    {
        return <<<TEMPLATE_STRING
        <?php
        use Phnet\Core\Assembly;
        
        require '/lib/phnet/Phnet.Core.phar';
        Assembly::init(__DIR__, '{$pharName}');
        Assembly::entrypoint(\$argv);
        TEMPLATE_STRING;
    }

    public static function getExecutableStub($pharName){
        return <<<TEMPLATE_STRING
        <?php
        use Phnet\Core\Assembly;
        
        Phar::mapPhar();
        require '/lib/phnet/Phnet.Core.phar';
        Assembly::init(__DIR__, '{$pharName}');
        Assembly::entrypoint(\$argv);
        __HALT_COMPILER();
        TEMPLATE_STRING;
    }

    public static function getExecutableAutoload(string $pharName)
    {
        return <<<TEMPLATE_STRING
<?php

\$innerPhar = '{$pharName}';
\$innerPharPath = "phar://{\$innerPhar}";
\$manifest = \$mainManifest = json_decode(file_get_contents("{\$innerPharPath}/manifest.json"), true);

\$types = [];
foreach (\$manifest['types'] as \$type => \$path) {
    \$types[\$type] = "{\$innerPharPath}/{\$path}";
}

spl_autoload_register(function (string \$entity) use (\$types) {
    if (key_exists(\$entity, \$types))
        require \$innerPharPath.DIRECTORY_SEPARATOR.\$types[\$entity];
}, false, true);

foreach (\$types as \$type => \$path) {
    class_exists(\$type);
}
TEMPLATE_STRING;
    }
}