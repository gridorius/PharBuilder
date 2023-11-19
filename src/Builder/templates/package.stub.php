<?php
 $manifestPath = 'phar://' . __FILE__.'/package.manifest.json';
 return json_decode(file_get_contents($manifestPath), true);
__HALT_COMPILER();