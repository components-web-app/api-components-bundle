<?php

/*
 * This file is part of the Silverback API Components Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Silverback\ApiComponentsBundle\Tests\Functional\TestBundle\Entity;

use ApiPlatform\Metadata\ApiResource;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Silverback\ApiComponentsBundle\Entity\Core\AbstractPageData;

#[ApiResource]
#[ORM\Entity]
class PageDataWithVariants extends AbstractPageData
{
    /** @var Collection<int, DummyPageDataVariant> */
    #[ORM\OneToMany(targetEntity: DummyPageDataVariant::class, mappedBy: 'pageData')]
    public Collection $variants;

    #[ORM\ManyToOne(targetEntity: DummyPageDataSupplier::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    public ?DummyPageDataSupplier $supplier = null;

    public function __construct()
    {
        $this->variants = new ArrayCollection();
    }
}
