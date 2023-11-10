<?php

use Phnet\Builder\ExecutablePacker;

include 'src/Builder/Manifest.php';
include 'src/Builder/Constants.php';
include 'src/Builder/Templates.php';
include 'src/Builder/ExcludeRegexDirectoryIteratorHandler.php';
include 'src/Builder/Builder.php';
include 'src/Builder/BuildDirector.php';
include 'src/Builder/PackagePacker.php';
include 'src/Builder/ExecutablePacker.php';
include 'src/Builder/EntityFinder.php';
include 'src/Builder/ProjectConfig.php';
include 'src/Builder/FIleScanning/Result.php';
include 'src/Builder/FIleScanning/Scanner.php';

(new \Phnet\Builder\BuildDirector(getcwd().'/src/Core/'))
    ->build('/lib/phnet');

(new \Phnet\Builder\BuildDirector(getcwd().'/src/Builder/'))
    ->build(getcwd().'/bin');

ExecutablePacker::pack(getcwd().'/bin', 'Phnet.Builder','builder', 'build');

