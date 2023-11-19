<?php

namespace Phnet\Builder\Async;

class Async
{
    public function fork(\Closure $action){
        $pid = pcntl_fork();
        if ($pid == -1) {
            throw new \Exception('Fork exception');
        } else if ($pid) {

        } else {
            $action();
            exit();
        }
    }
}