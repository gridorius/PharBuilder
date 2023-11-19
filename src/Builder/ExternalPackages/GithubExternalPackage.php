<?php

namespace Phnet\Builder\ExternalPackages;

use Phnet\Builder\BuildDirector;
use Phnet\Builder\Console\MultilineOutput;
use Phnet\Builder\Console\PercentEcho;
use Phnet\Builder\ExternalHelper;
use Phnet\Builder\HttpClient\Client;
use Phnet\Builder\PackageManager;

class GithubExternalPackage implements IExternalPackage
{
    protected $manager;

    public function __construct(PackageManager $manager)
    {
        $this->manager = $manager;
    }

    public function getName(): string
    {
        return 'github';
    }

    public function restore($reference)
    {
        [$name, $version] = ExternalHelper::getExternalPackageNameAndVersion($reference);
        if ($this->manager->findLocal($name, $version))
            return;

        if (!empty($version)) {
            $page = 1;
            do {
                $tags = $this->getTags($reference, 100, $page++);
                if (count($tags) == 0) break;
                foreach ($tags as $tag) {
                    preg_match("/(?<version>\d+\.\d+.\d+)/", $tag['name'], $tagName);
                    if (!empty($tagName['version']) && fnmatch($version, $tagName['version'])) {
                        $selectedTag = $tag;
                        break 2;
                    }
                }
            } while (true);
            if (empty($selectedTag))
                throw new \Exception("Tag version {$version} not found in {$reference['owner']}/{$reference['repo']}");
        } else {
            $tags = $this->getTags($reference, 1, 1);
            $selectedTag = reset($tags);
        }

        $projectTmpPath = $this->downloadPackage($name, $version, $reference, $selectedTag['tarball_url']);
        if (!empty($reference['proj']))
            file_put_contents(
                $projectTmpPath . DIRECTORY_SEPARATOR . $name . '.proj.json',
                json_encode($reference['proj'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
            );
        $director = new BuildDirector($this->manager, $projectTmpPath);
        $director->buildPackage();
        unlink($projectTmpPath);
    }

    protected function downloadPackage(string $name, string $version, array $reference, string $url): string
    {
        $projectTmpPath = sys_get_temp_dir() . DIRECTORY_SEPARATOR . $name . '_tmp_project';
        $row = MultilineOutput::getInstance()->createRow();
        Client::get($url)
            ->setHeaders('User-Agent: phnet')
            ->setProgressFunction(function ($ch, $total, $downloaded) use ($reference, $version, $row) {
                $row->update(PercentEcho::getBytesString($downloaded, "Download github package {$reference['owner']}/{$reference['repo']}({$version})"));
            })
            ->save(sys_get_temp_dir() . DIRECTORY_SEPARATOR . $name . '.tar.gz')
            ->extractTgzTo($projectTmpPath)
            ->delete();

        $dirit = new \DirectoryIterator($projectTmpPath);
        foreach ($dirit as $finfo) {
            if ($finfo->isDir() && !$finfo->isDot()) {
                $projectTmpPath .= DIRECTORY_SEPARATOR . $finfo->getFilename();
                break;
            }
        }

        return $projectTmpPath;
    }

    protected function getTags(array $reference, int $pageSize, int $page = 1): array
    {
        return Client::get("https://api.github.com/repos/{$reference['owner']}/{$reference['repo']}/tags?per_page={$pageSize}&page={$page}")
            ->setHeaders('User-Agent: phnet')
            ->send()
            ->json();
    }

    public function build(array $reference, string $outDir, array &$depends)
    {
        [$name, $version] = ExternalHelper::getExternalPackageNameAndVersion($reference);
        $found = $this->manager->findLocal($name, $version);
        if (empty($found))
            throw new \Exception("External reference {$reference['type']} {$name}({$version}) not found locally");

        $manifest = (new \Phar($found['path']))->getMetadata();
        $depends[$manifest->name] = $manifest->version;
    }
}