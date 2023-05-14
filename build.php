<?php
include 'src/Builder.php';
include 'src/Manifest.php';
include 'src/Constants.php';
include 'src/Templates.php';
include 'src/ExcludeRegexDirectoryIterator.php';

(new PharBuilder\Builder('/usr/src/builder/PharBuilder.proj.json', '/bin'))
    ->executable()
    ->buildPhar();