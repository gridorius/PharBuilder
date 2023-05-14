<?php

namespace PharBuilder;

use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

class ExcludeRegexDirectoryIterator implements \Iterator
{
    protected $exclude;
    protected $iterator;
    protected $handler;

    public function __construct(string $folder, string $pattern, ?array $exclude)
    {
        $this->exclude = $exclude;
        $this->iterator = new \RegexIterator(new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($folder, FilesystemIterator::SKIP_DOTS)
        ), $pattern, \RecursiveRegexIterator::GET_MATCH);
        $this->iterator->rewind();

        $this->handler = is_null($exclude) ? [$this, 'currentBase'] : [$this, 'currentExcluded'];
    }

    public function currentBase(): array
    {
        return [$this->iterator->key(), $this->iterator->getSubPathname()];
    }

    public function currentExcluded(): array
    {
        foreach ($this->exclude as $pattern) {
            if (preg_match($pattern, $this->iterator->key())) {
                $this->iterator->next();
                break;
            }
        }

        return $this->current();
    }

    public function current(): array
    {
        return ($this->handler)();
    }

    public function next(): void
    {
        $this->iterator->next();
    }

    public function key()
    {
        return $this->iterator->key();
    }

    public function valid(): bool
    {
        return $this->iterator->valid();
    }

    public function rewind(): void
    {
        $this->iterator->rewind();
    }
}
