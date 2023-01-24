<?php

namespace Silverback\ApiComponentsBundle\Factory\Uploadable;

interface UploadableUrlGenerator
{
    public function generateUrl(object $object, string $fileProperty): string;
}
