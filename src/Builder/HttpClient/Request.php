<?php

namespace Phnet\Builder\HttpClient;

use Exception;

class Request
{
    protected $ch;

    public function __construct($ch)
    {
        $this->ch = $ch;
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    }

    public function setHeaders(...$headers): Request
    {
        curl_setopt($this->ch, CURLOPT_HTTPHEADER, $headers);
        return $this;
    }

    public function setProgressFunction(\Closure $progress): Request
    {
        curl_setopt($this->ch, CURLOPT_NOPROGRESS, false);
        curl_setopt($this->ch, CURLOPT_PROGRESSFUNCTION, $progress);
        return $this;
    }

    public function send(): Response
    {
        $headers = [];
        curl_setopt($this->ch, CURLOPT_HEADERFUNCTION,
            function ($curl, $header) use (&$headers) {
                $len = strlen($header);
                $header = explode(':', $header, 2);
                if (count($header) < 2) // ignore invalid headers
                    return $len;

                $headers[strtolower(trim($header[0]))][] = trim($header[1]);

                return $len;
            }
        );
        $result = curl_exec($this->ch);
        if ($error = curl_error($this->ch))
            throw new Exception($error);
        curl_close($this->ch);
        return new Response($headers, $result);
    }

    public function save(string $path)
    {
        $headers = [];
        curl_setopt($this->ch, CURLOPT_HEADERFUNCTION,
            function ($curl, $header) use (&$headers) {
                $len = strlen($header);
                $header = explode(':', $header, 2);
                if (count($header) < 2) // ignore invalid headers
                    return $len;

                $headers[strtolower(trim($header[0]))][] = trim($header[1]);

                return $len;
            }
        );
        $result = curl_exec($this->ch);
        if ($error = curl_error($this->ch))
            throw new Exception($error);
        curl_close($this->ch);
        $dirname = dirname($path);
        if (!is_dir($dirname))
            mkdir($dirname, 0755, true);

        file_put_contents($path, $result);
        return new DownloadResult($headers, $path);
    }
}