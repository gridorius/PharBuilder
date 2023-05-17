<?php

namespace Phnet\Builder;

class FluidConsole
{
    /** @var FluidRow[] */
    protected $lines = [];
    protected $cursor = 0;

    public function row(string $pattern): FluidRow
    {
        return $this->lines[] = new FluidRow($this, count($this->lines), $pattern);
    }

    public function removeRow(int $line)
    {
        array_splice($this->lines, $line - 1, 1);
    }

    public function rewind()
    {
        echo "\033[{$this->cursor}F";
        $this->cursor = 0;
    }

    public function clearLine()
    {
        echo "\033[2K";
    }

    public function clear()
    {
        $this->clearAllAfterCursor();
    }

    protected function clearAllAfterCursor()
    {
        echo "\033[0J";
    }

    public function toLastLine()
    {
        $lines = 0;
        foreach ($this->lines as $line) {
            $lines += $line->getRows();
        }

        echo "\033[{$lines}E";
    }

    protected function updateLines()
    {
        foreach ($this->lines as $key => $line) {
            $line->setLine($key);
            $line->write();
        }
    }

    public function write()
    {
        $redraw = false;
        foreach ($this->lines as $key => $line) {
            if ($line->getBeforeRows() > $line->getRows()) {
                $clearRows = $line->getBeforeRows();
                echo "\033[{$clearRows}M";
                $redraw = true;
            }

            if ($line->isChanged() || $redraw)
                $line->write();
            else
                echo "\033[1E";

            $this->cursor += $line->getRows();
        }
    }

}