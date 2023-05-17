<?php
include 'src/Builder/Builder.php';
include 'src/Builder/Manifest.php';
include 'src/Builder/Constants.php';
include 'src/Builder/Templates.php';
include 'src/Builder/ExcludeRegexDirectoryIteratorHandler.php';

(new Phnet\Builder\Builder(getcwd().'/src/Builder/PhnetBuilder.proj.json', getcwd().'/bin'))
    ->executable()
    ->buildProjectReferences()
    ->buildPhar();

(new Phnet\Builder\Builder(getcwd().'/src/Core/Core.proj.json', getcwd().'/bin'))
    ->buildPhar();