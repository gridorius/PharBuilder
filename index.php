<?php

include './Builder.php';
include './Program.php';
include './RecursiveFinder.php';

(new PharBuilder\Program())->main($argv);

