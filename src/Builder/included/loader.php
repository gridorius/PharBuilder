<?php
$manifest = json_decode(file_get_contents(__DIR__.'/manifest.json'), true);
spl_autoload_register(function($entity) use ($manifest){
    if(key_exists($entity, $manifest['types']))
        require __DIR__.DIRECTORY_SEPARATOR.$manifest['types'][$entity];
});

foreach ($manifest['types'] as $type => $path)
    class_exists($type);