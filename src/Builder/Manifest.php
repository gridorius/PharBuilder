<?php

namespace Phnet\Builder;

class Manifest
{
    public $name = '';
    public $version = '';
    public $types = [];
    public $files = [];
    public $resources = [];
    public $hashes = [];
    public $pharDepends = [];
}