<?php

namespace Phnet\Builder\Source;

use Exception;
use Phnet\Builder\Console\MultilineOutput;
use Phnet\Builder\HttpClient\Client;

class Source
{
    protected $manager;
    protected array $data;

    public function __construct(SourceManager $manager, array $sourceData)
    {
        $this->manager = $manager;
        $this->data = $sourceData;
    }

    public function getSourcePath(): string
    {
        return $this->data['source'];
    }

    public function updateToken()
    {
        $response = Client::post($this->data['service'] . '/token/update', [
            'refresh' => $this->data['refresh']
        ])->send();

        $data = $response->json();
        if (!$data)
            throw new Exception('Authorize exception');

        $data['source'] = $this->data['source'];
        $this->manager->setCredentials($data);
    }

    public function auth(string $login, string $password)
    {
        $response = Client::post($this->data['service'] . '/auth', [
            'login' => $login,
            'password' => $password,
        ])->send();

        $data = $response->json();
        if (!$data)
            throw new Exception('Authorize exception');

        $data['source'] = $this->data['source'];
        $this->manager->setCredentials($data);
    }

    public function uploadPackage($data): string
    {
        $result = Client::post($this->data['source'] . '/packages', $data)
            ->setHeaders("Authorization: Bearer {$this->data['token']}")
            ->send()
            ->json();
        if (is_null($result))
            throw new Exception('Result not converted');

        if (!empty($result['error']))
            throw new Exception($result['error']);

        return $result['PackageId'];
    }

    public function findPackages(array $packages)
    {
        $result = Client::post($this->data['source'] . '/packages/find', $packages)
            ->send()
            ->json();
        if (is_null($result))
            throw new Exception('Result not converted');

        if (!empty($result['error']))
            throw new Exception($result['error']);

        return $result;
    }

    public function downloadPackage(string $name, string $version): string
    {
        $row = MultilineOutput::getInstance()->createRow();
        $result = Client::get($this->data['source'] . "/packages/{$name}/v/{$version}")
            ->setProgressFunction(function ($resource, $download_size, $downloaded) use ($name, $version, $row) {
                $percent = ($downloaded / $download_size) * 100;
                $row->update("Donload package {$name}({$version}) - {$percent}\r");
            })
            ->send();

        if ($result->getHeader('Content-Type') == 'application/json')
            throw new Exception($result->json()['error']);

        return $result->text();
    }
}