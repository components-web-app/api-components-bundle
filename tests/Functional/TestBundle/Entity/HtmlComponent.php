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
use Silverback\ApiComponentsBundle\Entity\Core\AbstractComponent;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * This is the component used in the tutorial. It is not written for tests and can be deleted.
 *
 * @author Daniel West <daniel@silverback.is>
 * @ApiResource
 * @ORM\Entity
 */
class HtmlComponent extends AbstractComponent
{
    /**
     * @ORM\Column(nullable=false)
     * @Assert\NotBlank()
     */
    public string $html;
}
