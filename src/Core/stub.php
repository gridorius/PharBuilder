<?php
Phar::mapPhar();
$path = 'phar://'.__FILE__.'/types/';
require $path.'Resource.php';
require $path.'Resources.php';
require $path.'Assembly.php';
__HALT_COMPILER();