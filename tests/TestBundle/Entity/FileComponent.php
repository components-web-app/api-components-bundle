<?php

namespace Silverback\ApiComponentBundle\Tests\TestBundle\Entity;

use Silverback\ApiComponentBundle\Entity\Content\Component\FileInterface;
use Silverback\ApiComponentBundle\Entity\Content\Component\FileTrait;

class FileComponent implements FileInterface
{
    use FileTrait {
        getImagineFilters as getImagineFiltersOld;
    }

    public static function getImagineFilters(): array
    {
        return [
            'thumbnailPath' => 'thumbnail'
        ];
    }
}
