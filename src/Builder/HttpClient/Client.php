<?php

namespace Phnet\Builder\HttpClient;

class Client
{
    public static function post(string $url, $data): Request
    {
        $ch = static::initCurl($url);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        return new Request($ch);
    }

    public static function get(string $url, $data = [])
    {
        $ch = static::initCurl($url . (empty($data) ? '' : '?' . http_build_query($data)));
        return new Request($ch);
    }

    protected static function initCurl(string $url)
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        return $ch;
    }
}