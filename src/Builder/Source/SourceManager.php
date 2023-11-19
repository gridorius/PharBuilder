<?php

namespace Phnet\Builder\Source;

class SourceManager
{
    protected array $authData = [];
    protected string $authDataFilePath;

    public function __construct(array $config)
    {
        $this->authDataFilePath = $config['authDataPath'];
        $this->load();
    }

    public function isAuthorized(string $service): bool
    {
        return !empty($this->authData[$service]);
    }

    public function getCredentials(string $service)
    {
        return $this->authData[$service];
    }

    public function setCredentials(array $data)
    {
        $this->authData[$data['service']] = $data;
        $this->save();
    }

    public function getSource(string $name): Source
    {
        return new Source($this, $this->authData[$name] ?? ['source' => $name]);
    }

    public function getSources(array $sources): array{
        $result = [];
        foreach ($sources as $source){
            $result[] = new Source($this, $this->authData[$source] ?? ['source' => $source]);
        }
        return $result;
    }

    public function auth(string $source, string $login, string $password)
    {
        $this->getSource($source)->auth($login, $password);
    }

    public function load()
    {
        if (file_exists($this->authDataFilePath))
            $this->authData = json_decode(file_get_contents($this->authDataFilePath), true);
    }

    public function save()
    {
        if (!is_dir(dirname($this->authDataFilePath)))
            mkdir($this->authDataFilePath, 0755, true);

        file_put_contents($this->authDataFilePath, json_encode($this->authData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    }
}