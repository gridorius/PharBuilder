<?php

namespace Phnet\Builder;

class FluidRow
{
    protected $console;
    protected $pattern;
    protected $line;
    protected $changed = false;
    protected $rows = 1;
    protected $beforeRows = 1;
    protected $data = '';

    public function __construct(FluidConsole $console, int $line, string $pattern)
    {
        $this->line = $line;
        $this->console = $console;
        $this->pattern = $pattern;
    }

    public function update(...$values)
    {
        $this->data = sprintf($this->pattern, ...$values) . PHP_EOL;
        $this->beforeRows = $this->rows;
        $this->rows = substr_count($this->data, "\n");
        $this->changed = true;
        return $this;
    }

    public function write()
    {
        $this->console->clearLine();
        $this->changed = false;
        $this->beforeRows = $this->rows;
        echo $this->data;
    }

    public function isChanged(): bool
    {
        return $this->changed;
    }

    public function getRows(): int
    {
        return $this->rows;
    }

    public function getBeforeRows(): int
    {
        return $this->beforeRows;
    }

    public function setLine(int $line)
    {
        $this->line = $line;
    }

    public function delete()
    {
        $this->console->removeRow($this->line);
    }
}