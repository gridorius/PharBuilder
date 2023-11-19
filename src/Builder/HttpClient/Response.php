<?php

namespace Phnet\Builder\HttpClient;

class Response
{
    protected $headers = [];
    protected string $data;

    public function __construct(array $headers, string $data)
    {
        $this->headers = $headers;
        $this->data = $data;
    }

    public function getHeader(string $name): string{
        return $this->headers[$name];
    }

    public function text(): string{
        return $this->data;
    }

    public function json(): ?array{
        return json_decode($this->data, true);
    }
}