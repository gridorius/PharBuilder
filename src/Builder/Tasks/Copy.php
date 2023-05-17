<?php

namespace Phnet\Builder\Tasks;

class Copy extends TaskBase
{
    public function execute()
    {
        copy($this->replaceBuildParams($this->params['from']), $this->replaceBuildParams($this->params['to']));
    }
}