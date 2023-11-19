<?php

namespace Phnet\Builder\Console;

class MultilineOutput
{
    protected static $instance;
    /** @var Row[] */
    protected array $rows = [];
    protected array $lastOutput = [];
    protected array $params = [];

    protected function __construct()
    {

    }

    public static function getInstance(): MultilineOutput
    {
        if (!static::$instance)
            static::$instance = new static();
        return static::$instance;
    }

    public function setParams(array $params){
        $this->params = array_merge($this->params, $params);
    }

    public function getParam(string $name){
        return $this->params[$name];
    }

    public function createRow(string $key = null): Row
    {
        if (!empty($key))
            return $this->rows[$key] = new Row($this);
        return $this->rows[] = new Row($this);
    }

    public function getRow(string $key): Row
    {
        return $this->rows[$key];
    }

    public function update()
    {
        foreach ($this->lastOutput as $item) {
            echo "\033[2K\033[1F";
        }

        $output = [];
        foreach ($this->rows as $row) {
            if (!$row->isDeleted())
                $row->fillOutput($output);
        }

        $this->lastOutput = $output;
        foreach ($output as $row)
            echo $row . "\033[1E";
    }
}