<?php

namespace PharBuilder\Tasks;

class Command extends TaskBase
{
    public function execute()
    {
        $command = $this->replaceBuildParams($this->params['command']);
        echo shell_exec($command);
    }
}