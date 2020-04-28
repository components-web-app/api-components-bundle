<?php

/*
 * This file is part of the Silverback API Component Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Silverback\ApiComponentBundle\Entity\Uploadable;

use ApiPlatform\Core\Annotation\ApiResource;
use Doctrine\ORM\Mapping as ORM;
use Silverback\ApiComponentBundle\Annotation as Silverback;
use Silverback\ApiComponentBundle\Entity\Utility\IdTrait;
use Silverback\ApiComponentBundle\Entity\Utility\TimestampedTrait;
use Silverback\ApiComponentBundle\Model\Uploadable\FileData;
use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * @author Daniel West <daniel@silverback.is>
 *
 * @Silverback\MediaObject
 * @Silverback\Timestamped
 * @ApiResource
 * @ORM\Entity
 */
class MediaObject
{
    use IdTrait;
    use TimestampedTrait;

    /**
     * @Assert\NotNull(groups={"MediaObject:write"})
     */
    public File $file;

    public string $filePath;

    public bool $temporary = true;

    public FileData $fileData;

    public function __construct()
    {
        $this->setId();
    }
}
