<?php

use PharBuilder\Builder;

include 'Builder.php';
include 'Program.php';
include 'RecursiveFinder.php';

(new Builder($argv[1]))->build();