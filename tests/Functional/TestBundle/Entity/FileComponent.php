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

namespace Silverback\ApiComponentBundle\Tests\Functional\TestBundle\Entity;

use ApiPlatform\Core\Annotation\ApiResource;
use Doctrine\ORM\Mapping as ORM;
use Silverback\ApiComponentBundle\Entity\Core\AbstractComponent;
use Silverback\ApiComponentBundle\Entity\Utility\FileInterface;
use Silverback\ApiComponentBundle\Entity\Utility\FileTrait;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * @author Daniel West <daniel@silverback.is>
 * @ApiResource
 * @ORM\Entity
 */
class FileComponent extends AbstractComponent implements FileInterface
{
    use FileTrait;

    /**
     * @var string a greeting name
     *
     * @ORM\Column
     * @Assert\NotBlank
     */
    public string $name = '';
}
