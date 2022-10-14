<?php

namespace PharBuilder;

class RecursiveFinder
{
    public function find($folder, $pattern)
    {
        $found = [];
        $folderName = rtrim($folder, '/');
        $dir = opendir($folderName); // открываем текущую папку

        while (($file = readdir($dir)) !== false) {
            $file_path = "$folderName/$file";
            if ($file == '.' || $file == '..') continue;

            if (is_file($file_path)) {
                if (preg_match($pattern, $file))
                    $found[] = $file_path;
            } elseif (is_dir($file_path)) {
                $res = $this->find($file_path, $pattern);
                $found = array_merge($found, $res);
            }

        }

        closedir($dir);

        return $found;
    }
}