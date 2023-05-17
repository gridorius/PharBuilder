<?php

namespace Phnet\Builder\Tasks;

class Delete extends TaskBase
{

    public function execute()
    {
        unlink($this->replaceBuildParams($this->params['path']));
    }
}