<?php

use PharBuilder\Builder;

include 'Builder.php';
include 'RecursiveFinder.php';

(new Builder($argv[1]))->build();