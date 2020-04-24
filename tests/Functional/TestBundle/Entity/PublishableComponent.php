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
use Silverback\ApiComponentBundle\Annotation as Silverback;
use Silverback\ApiComponentBundle\Entity\Core\AbstractComponent;
use Silverback\ApiComponentBundle\Entity\Utility\PublishableInterface;
use Silverback\ApiComponentBundle\Entity\Utility\PublishableTrait;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * @author Daniel West <daniel@silverback.is>
 * @Silverback\Publishable
 * @ApiResource
 * @ORM\Entity
 */
class PublishableComponent extends AbstractComponent implements PublishableInterface
{
    use PublishableTrait;

    /**
     * @var string a reference for this component
     *
     * @ORM\Column
     * @Assert\NotBlank(groups={"PublishableComponent:published"})
     */
    public string $reference = '';
}
