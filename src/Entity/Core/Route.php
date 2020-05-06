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

namespace Silverback\ApiComponentsBundle\Entity\Core;

use ApiPlatform\Core\Annotation\ApiResource;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Silverback\ApiComponentsBundle\Annotation as Silverback;
use Silverback\ApiComponentsBundle\Entity\Utility\IdTrait;
use Silverback\ApiComponentsBundle\Entity\Utility\TimestampedTrait;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * @author Daniel West <daniel@silverback.is>
 *
 * @Silverback\Timestamped
 * @ApiResource(
 *     collectionOperations={
 *         "get"={"security"="is_granted('ROLE_SUPER_ADMIN')"},
 *         "post"
 *     }
 * )
 * @Assert\Expression(
 *     "!(this.pageTemplate == null & this.pageData == null) & !(this.pageTemplate != null & this.pageData != null)",
 *     message="Please specify either pageTemplate or pageData, not both."
 * )
 */
class Route
{
    use IdTrait;
    use TimestampedTrait;

    /**
     * @Assert\NotNull()
     */
    public string $route;

    /**
     * @Assert\NotNull()
     */
    public string $name;

    public ?Route $redirect = null;

    public Collection $redirectedFrom;

    public ?PageTemplate $pageTemplate = null;

    public ?AbstractPageData $pageData = null;

    public function __construct()
    {
        $this->redirectedFrom = new ArrayCollection();
    }
}
