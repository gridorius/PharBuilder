<?php

namespace Phnet\Builder;

use Closure;
use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RecursiveRegexIterator;
use RegexIterator;

class ExcludeRegexDirectoryIteratorHandler
{
    protected $exclude;
    protected $iterator;
    protected $handler;

    public function __construct(string $folder, string $pattern, ?array $exclude)
    {
        $this->exclude = $exclude;
        $this->iterator = new RegexIterator(new RecursiveIteratorIterator(
            new WinSupportDirIterator($folder, FilesystemIterator::SKIP_DOTS)
        ), $pattern, RecursiveRegexIterator::GET_MATCH, RegexIterator::USE_KEY);
        $this->iterator->rewind();

        $this->handler = is_null($exclude) ? [$this, 'handleBase'] : [$this, 'handleExcluded'];
    }

    public function handle(Closure $delegate)
    {
        foreach ($this->iterator as $item) {
            ($this->handler)($delegate);
        }
    }

    protected function winReplace(string $input)
    {
        return str_replace("\\", "/", $input);
    }

    protected function handleExcluded(Closure $delegate): void
    {
        foreach ($this->exclude as $pattern) {
            if (preg_match($pattern, $this->iterator->key())) {
                $this->iterator->next();
                return;
            }
        }

        $this->handleBase($delegate);
    }

    protected function handleBase(Closure $delegate): void
    {
        $delegate($this->iterator->key(), $this->iterator->getSubPathname());
    }
}

class WinSupportDirIterator extends RecursiveDirectoryIterator
{
    public function key()
    {
        return $this->winReplace(parent::key());
    }

    protected function winReplace(string $input)
    {
        return str_replace("\\", "/", $input);
    }

    public function getSubPathname()
    {
        return $this->winReplace(parent::getSubPathname());
    }
}
