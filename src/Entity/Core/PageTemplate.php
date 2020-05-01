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
use Doctrine\ORM\Mapping as ORM;
use Silverback\ApiComponentsBundle\Entity\Utility\ComponentGroupsTrait;
use Silverback\ApiComponentsBundle\Entity\Utility\UiTrait;

/**
 * @author Daniel West <daniel@silverback.is>
 * @ApiResource(attributes={"output"=PageTemplate::class})
 * @ORM\Entity
 * @ORM\AssociationOverrides({
 *     @ORM\AssociationOverride(name="route", inversedBy="pageTemplate"),
 *     @ORM\AssociationOverride(name="componentGroups", inversedBy="pageTemplates")
 * })
 */
class PageTemplate extends AbstractPage
{
    use UiTrait;
    use ComponentGroupsTrait;

    /**
     * @ORM\ManyToOne(targetEntity="Silverback\ApiComponentsBundle\Entity\Core\Layout", inversedBy="pageTemplates")
     * @ORM\JoinColumn(nullable=true, onDelete="SET NULL")
     */
    public ?Layout $layout;

    public function __construct()
    {
        parent::__construct();
        $this->initComponentGroups();
    }

    public function __clone()
    {
        parent::__construct();
    }
}
