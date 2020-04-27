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

namespace Silverback\ApiComponentBundle\Entity\Core;

use Doctrine\ORM\Mapping as ORM;
use Silverback\ApiComponentBundle\Annotation as Silverback;
use Silverback\ApiComponentBundle\Entity\Utility\IdTrait;
use Silverback\ApiComponentBundle\Entity\Utility\TimestampedTrait;

/**
 * @author Daniel West <daniel@silverback.is>
 *
 * @Silverback\Timestamped
 * @ORM\MappedSuperclass
 *
 * @internal
 */
abstract class AbstractPage
{
    use IdTrait;
    use TimestampedTrait;

    /**
     * @ORM\OneToOne(targetEntity="Silverback\ApiComponentBundle\Entity\Core\Route", cascade={"persist"})
     */
    public ?Route $route;

    /**
     * This will be se so that when auto-generating a route for a newly created
     * PageTemplate / PageData, we can prepend parent routes.
     *
     * @ORM\OneToOne(targetEntity="Silverback\ApiComponentBundle\Entity\Core\Route", cascade={"persist"})
     */
    public ?Route $parentRoute;

    /**
     * If true, then the PageTemplate/PageData is nested within the parentRoute.
     * So not only will any auto-generated route have the parent route prefixes,
     * the front end should expect to load the PageTemplate/PageData nested within
     * the parent page(s) up to the point where a parent is defined as not nested or
     * does not have a parent route
     * E.g. the parent route's page may just be a Hero and some Tab navigation.
     *
     * @ORM\Column(type="boolean")
     */
    public bool $isNested = true;

    /**
     * @ORM\Column()
     */
    public string $title = 'Unnamed Page';

    /**
     * @ORM\Column(nullable=true)
     */
    public ?string $metaDescription;

    public function __construct()
    {
        $this->setId();
    }
}
