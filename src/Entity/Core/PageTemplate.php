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

use ApiPlatform\Core\Annotation\ApiResource;
use Doctrine\ORM\Mapping as ORM;
use Silverback\ApiComponentBundle\Entity\Utility\UiTrait;

/**
 * @author Daniel West <daniel@silverback.is>
 * @ApiResource(attributes={"output": "Silverback\ApiComponentBundle\Entity\Core\PageTemplate"})
 * @ORM\Entity
 * @ORM\AssociationOverrides({
 *     @ORM\AssociationOverride(name="routes", inversedBy="pageTemplate"),
 *     @ORM\AssociationOverride(name="componentGroups", inversedBy="pageTemplates")
 * })
 */
class PageTemplate extends AbstractPage
{
    use UiTrait;

    /**
     * @ORM\ManyToOne(targetEntity="Silverback\ApiComponentBundle\Entity\Core\Layout", inversedBy="pageTemplates")
     * @ORM\JoinColumn(nullable=true, onDelete="SET NULL")
     *
     * @var Layout|null
     */
    public ?Layout $layout;

    public function __construct()
    {
        parent::__construct();
        $this->initComponentGroups();
    }
}
