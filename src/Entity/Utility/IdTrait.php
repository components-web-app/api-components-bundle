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

namespace Silverback\ApiComponentsBundle\Entity\Utility;

use ApiPlatform\Core\Annotation\ApiProperty;
use Doctrine\ORM\Mapping as ORM;

/**
 * Reusable trait by application developer so keep annotations as we cannot map with XML.
 *
 * @author Daniel West <daniel@silverback.is>
 */
trait IdTrait
{
    /**
     * Must allow return `null` for lowest dependencies.
     *
     * @ORM\Id
     * @ORM\Column(type="string", unique=true)
     * @ORM\GeneratedValue(strategy="UUID")
     * @ApiProperty(readable=false)
     */
    protected $id;

    public function getId()
    {
        return $this->id;
    }
}
