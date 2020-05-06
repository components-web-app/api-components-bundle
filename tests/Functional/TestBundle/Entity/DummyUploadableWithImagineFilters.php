<?php

/*
 * This file is part of the Silverback API Components Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Silverback\ApiComponentsBundle\Tests\Functional\TestBundle\Entity;

use ApiPlatform\Core\Annotation\ApiResource;
use Doctrine\ORM\Mapping as ORM;
use Silverback\ApiComponentsBundle\Annotation as Silverback;
use Silverback\ApiComponentsBundle\Entity\Utility\IdTrait;
use Silverback\ApiComponentsBundle\Entity\Utility\ImagineFiltersInterface;
use Silverback\ApiComponentsBundle\Entity\Utility\UploadableTrait;
use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\HttpFoundation\Request;

/**
 * @author Daniel West <daniel@silverback.is>
 *
 * @Silverback\Uploadable
 * @ApiResource
 * @ORM\Entity
 */
class DummyUploadableWithImagineFilters implements ImagineFiltersInterface
{
    use IdTrait;
    use UploadableTrait;

    /** @Silverback\UploadableField(adapter="local") */
    public ?File $file = null;

    public function getImagineFilters(string $property, ?Request $request): array
    {
        return ['thumbnail', 'square_thumbnail'];
    }
}
