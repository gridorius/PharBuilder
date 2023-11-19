<?php

namespace Phnet\Builder\Console;

class PercentEcho
{
    protected $total = 0;
    protected $message = '';
    protected $lastValue = 0;

    public function __construct(int $total, string $message)
    {
        $this->total = $total;
        $this->message = $message;
    }

    public function show(int $count)
    {
        $percent = $this->calc($count);
        echo $this->message . ' - ' . $percent . '%' . "\r";
    }

    public static function getBytesString(int $bytes, string $message): string
    {
        if ($bytes == 0) {
            return $message . ' - ' . $bytes . "B \r";
        }

        $units = ['B', 'KB', 'MB', 'GB', 'TB'];

        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);

        $bytes /= pow(1024, $pow);
        $bytesStr = round($bytes, 2) . $units[$pow];
        return $message . ' - ' . $bytesStr . "\r";;
    }

    public function showInc()
    {
        $this->show($this->lastValue++);
    }

    public function close()
    {
        echo "\r\n";
    }

    protected function calc(int $count): float
    {
        return round(($count / $this->total) * 100);
    }

    public static function init(int $total, string $message): PercentEcho
    {
        return new static($total, $message);
    }
}