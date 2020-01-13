<?php

namespace Silverback\ApiComponentBundle\Tests\Functional\TestBundle\Entity;

use Silverback\ApiComponentBundle\Entity\Utility\FileInterface;
use Silverback\ApiComponentBundle\Entity\Utility\FileTrait;

class FileComponent implements FileInterface
{
    use FileTrait;

    public static function getImagineFilters(): array
    {
        return [
            'thumbnailPath' => 'thumbnail'
        ];
    }
}
