<?php

namespace Phnet\Builder\Console;

class Row
{
    protected $parent;
    protected $data;
    protected $deleted = false;
    protected $subData = [];
    protected $params = [];

    public function __construct(MultilineOutput $parent)
    {
        $this->parent = $parent;
    }

    public function update(string $data): Row
    {
        $this->data = $data;
        $this->parent->update();
        return $this;
    }

    public function updateSubData(array $subData, array $params = [])
    {
        $this->subData = $subData;
        $this->params = array_merge($this->params, $params);
        $this->parent->update();
    }

    public function addSubdata(string $item, int $max = 5)
    {
        $this->subData[] = $item;
        if (count($this->subData) > $max)
            array_shift($this->subData);

        $this->parent->update();
    }

    public function clearSubdata(): void{
        $this->subData = [];
    }

    public function fillOutput(array &$output): array
    {
        $output[] = preg_replace_callback("/\{(?<field>.+?)\}/", function ($matches) {
            $param = $this->params[$matches['field']] ?? $this->parent->getParam($matches['field']) ?? '';
            return is_callable($param) ? $param() : $param;
        }, $this->data);
        foreach ($this->subData as $item)
            $output[] = "\t" . $item;
        return $output;
    }

    public function delete()
    {
        $this->deleted = true;
        $this->parent->update();
    }

    public function isDeleted(): bool
    {
        return $this->deleted;
    }
}