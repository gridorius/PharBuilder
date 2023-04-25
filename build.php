<?php
include 'src/Builder.php';
include 'src/Manifest.php';
include 'src/Constants.php';

(new PharBuilder\Builder('/usr/src/builder', '/bin'))
    ->buildPhar();