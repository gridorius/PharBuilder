<?php

namespace PharBuilder;

class Program
{
    public function main($argv){
        $builder = new Builder($argv[1]);
        $builder->build();
    }
}