<?php

namespace Phnet\Builder\ExternalPackages;

use Phnet\Builder\PackageManager;

interface IExternalPackage
{
    public function __construct(PackageManager $manager);

    public function getName(): string;

    public function restore($reference);

    public function build(array $reference, string $outDir, array &$depends);
}