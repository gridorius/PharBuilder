<?php

namespace PharBuilder;

class IndexTemplate
{
    public static function get(string $pharName)
    {
        return <<<TEMPLATE_STRING
<?php
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_WARNING | E_ERROR);

\$innerPhar = '{$pharName}';

\$manifests = [];

\$manifests[] = \$mainManifest = json_decode(file_get_contents("phar://{\$innerPhar}/manifest.json"), true);
\$additional = \$mainManifest['depends'];

while (count(\$additional) > 0) {
    \$depend = array_shift(\$additional);
    \$manifests[] = \$dependManifest = json_decode(file_get_contents("phar://{\$depend['name']}.phar/manifest.json"), true);
    \$additional = array_merge(\$additional, \$dependManifest['depends']);
}

\$types = [];
foreach (\$manifests as \$manifest) {
    \$pharDir = __DIR__;
    \$pharPath = "phar://{\$pharDir}/{\$manifest['name']}.phar";
    foreach (\$manifest['types'] as \$type => \$path) {
        \$types[\$type] = "{\$pharPath}/{\$path}";
    }
}

spl_autoload_register(function (string \$entity) use (\$types) {
    if (key_exists(\$entity, \$types))
        require \$types[\$entity];
}, false, true);

\$main = array_shift(\$manifests);

foreach (array_reverse(\$manifests) as \$manifest) {
    require "{\$manifest['name']}.phar";
}

foreach (\$types as \$type => \$path) {
    class_exists(\$type);
}

require "{\$main['name']}.phar";
TEMPLATE_STRING;
    }
}