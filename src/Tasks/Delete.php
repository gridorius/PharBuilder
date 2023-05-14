<?php

namespace PharBuilder\Tasks;

class Delete extends TaskBase
{

    public function execute()
    {
        unlink($this->replaceBuildParams($this->params['path']));
    }
}