<?php

use Phnet\Builder\BuildDirector;
use Phnet\Builder\PackageManager;

include 'src/Builder/Manifests/ProjectManifest.php';
include 'src/Builder/Manifests/SingleManifest.php';
include 'src/Builder/Constants.php';
include 'src/Builder/Templates.php';
include 'src/Builder/Structure/Paths.php';
include 'src/Builder/Structure/Structure.php';
include 'src/Builder/Structure/StructureBuilder.php';
include 'src/Builder/Structure/PharBuilderBase.php';
include 'src/Builder/Structure/PharBuilder.php';
include 'src/Builder/BuildDirector.php';
include 'src/Builder/ExecutablePacker.php';
include 'src/Builder/EntityFinder.php';
include 'src/Builder/ProjectConfig.php';
include 'src/Builder/FIleScanning/Result.php';
include 'src/Builder/FIleScanning/Scanner.php';
include 'src/Builder/PackageManager.php';
include 'src/Builder/Console/Row.php';
include 'src/Builder/Console/MultilineOutput.php';

$config = include 'src/Builder/configuration.php';

$basePath = $config['linuxPackagePath'];
mkdir($basePath, 0755, true);
mkdir($basePath . '/usr/bin', 0755, true);
mkdir($basePath . '/var/lib', 0755, true);
mkdir($basePath . '/DEBIAN', 0755, true);


$libDirectory = $basePath . '/var/lib/phnet';

(new BuildDirector(new PackageManager($config), getcwd() . '/src/Core/'))
    ->build($libDirectory);

$builderPath = getcwd() . '/src/Builder/';
(new BuildDirector(new PackageManager($config), $builderPath))
    ->buildSingle($libDirectory, 'builder');

$builderConfig = \Phnet\Builder\ProjectConfig::getConfig($builderPath);
file_put_contents($basePath . '/DEBIAN/control', <<<CONTROL
Package: phnet
Version: {$builderConfig['version']}
Section: misc
Architecture: all
Depends: bash
Maintainer: gridorius <gridorius@yandex.ru>
Description: phar project builder.

CONTROL
);
file_put_contents($basePath . '/DEBIAN/dirs', <<<DIRS
/var/lib/phnet
{$config['packagePath']}
{$config['authDataPath']}
DIRS
);

file_put_contents('/phnet_version', $builderConfig['version']);


