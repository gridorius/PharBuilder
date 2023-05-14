<?php

namespace PharBuilder\Tasks;

abstract class TaskBase
{
    protected $params;
    protected $buildData;

    public function __construct(array $params, array $buildData)
    {
        $this->params = $params;
        $this->buildData = $buildData;
    }

    protected function replaceBuildParams(string $input){
        return preg_replace_callback("/\\$\((\w+?)\)/", function ($matches){
            if(isset($this->buildData[$matches[1]]))
                return $this->buildData[$matches[1]];

            throw new \Exception("Parameter {$matches[1]} not found");
        }, $input);
    }

    abstract public function execute();
}