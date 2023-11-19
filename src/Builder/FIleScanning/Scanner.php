<?php

namespace Phnet\Builder\FIleScanning;

use FilesystemIterator;

class Scanner
{
    public static function scanFiles(string $path): Result
    {
        $files = [];
        $recursiveDirectoryIterator = new \RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS);
        foreach ($recursiveDirectoryIterator as $item) {
            if ($item->isDir()) {
                $fileName = $item->getFilename();
                $iterator = $recursiveDirectoryIterator->getChildren();
                static::subScanFiles($path . DIRECTORY_SEPARATOR . $fileName, $fileName, $iterator, $files);
            } else {
                $fileName = $item->getFilename();
                $files[$path . DIRECTORY_SEPARATOR . $fileName] = $fileName;
            }
        }

        return new Result($files);
    }

    private static function subScanFiles(string $globalPath, string $innerPath, \RecursiveDirectoryIterator $iterator, &$scannedFiles)
    {
        $subIterators = [];
        $files = [];
        foreach ($iterator as $item) {
            if ($item->isDir()) {
                $subIterators[$item->getFilename()] = $iterator->getChildren();
            } else {
                $fileName = $item->getFilename();
                $files[$globalPath . DIRECTORY_SEPARATOR . $fileName] = $innerPath . DIRECTORY_SEPARATOR . $fileName;
            }
        }

        foreach ($files as $path => $subPath)
            $scannedFiles[$path] = $subPath;

        foreach ($subIterators as $dirName => $iterator) {
            static::subScanFiles(
                $globalPath . DIRECTORY_SEPARATOR . $dirName,
                $innerPath . DIRECTORY_SEPARATOR . $dirName,
                $iterator,
                $scannedFiles
            );
        }
    }

    public static function scanFilesWithoutSubprojects(string $path): Result
    {
        $files = [];
        $recursiveDirectoryIterator = new \RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS);
        foreach ($recursiveDirectoryIterator as $item) {
            if ($item->isDir()) {
                $fileName = $item->getFilename();
                $iterator = $recursiveDirectoryIterator->getChildren();
                static::subScanWithoutSubprojects($path . DIRECTORY_SEPARATOR . $fileName, $fileName, $iterator, $files);
            } else {
                $fileName = $item->getFilename();
                $files[$path . DIRECTORY_SEPARATOR . $fileName] = $fileName;
            }
        }

        return new Result($files);
    }

    private static function subScanWithoutSubprojects(string $globalPath, string $innerPath, \RecursiveDirectoryIterator $iterator, &$scannedFiles)
    {
        $subIterators = [];
        $files = [];
        foreach ($iterator as $item) {
            if ($item->isDir()) {
                $subIterators[$item->getFilename()] = $iterator->getChildren();
            } else {
                $fileName = $item->getFilename();
                if (preg_match("/proj\.json/", $fileName))
                    return;
                $files[$globalPath . DIRECTORY_SEPARATOR . $fileName] = $innerPath . DIRECTORY_SEPARATOR . $fileName;
            }
        }

        foreach ($files as $path => $subPath)
            $scannedFiles[$path] = $subPath;

        foreach ($subIterators as $dirName => $iterator) {
            static::subScanWithoutSubprojects(
                $globalPath . DIRECTORY_SEPARATOR . $dirName,
                $innerPath . DIRECTORY_SEPARATOR . $dirName,
                $iterator,
                $scannedFiles
            );
        }
    }
}