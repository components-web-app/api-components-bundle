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
use Silverback\ApiComponentsBundle\Entity\Utility\PublishableTrait;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * @author Daniel West <daniel@silverback.is>
 * @Silverback\Publishable
 * @ApiResource
 * @ORM\Entity
 */
class DummyPublishableWithValidation
{
    use IdTrait;
    use PublishableTrait;

    /**
     * This constraint will be applied on draft and published resources.
     *
     * @Assert\NotBlank
     */
    public string $name = '';

    /**
     * This constraint will be applied on published resources only.
     *
     * @Assert\NotBlank(groups={"DummyPublishableWithValidation:published"})
     */
    public string $description = '';
}
