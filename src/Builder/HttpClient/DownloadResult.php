<?php

namespace Phnet\Builder\HttpClient;

class DownloadResult
{
    protected array $headers;
    protected string $path;

    public function __construct(array $headers, string $path)
    {
        $this->headers = $headers;
        $this->path = $path;
    }

    public function getHeader(string $name): string{
        return $this->headers[$name];
    }

    public function getPath(): string{
        return $this->path;
    }

    public function extractTgzTo(string $path): DownloadResult{
        if(!is_dir($path))
            mkdir($path, 0755, true);

        $compressedPhar = new \PharData($this->path);
        $compressedPhar->decompress();

        $decompressedName = substr($this->path, 0, -3);
        $decompressedPhar = new \PharData($decompressedName);
        $decompressedPhar->extractTo($path);
        unlink($decompressedName);
        return $this;
    }

    public function delete(): void{
        unlink($this->path);
    }
}