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

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * We must define this as an API resource, otherwise when serializing and the relation is to this class,
 * API Platform does not know that it will be a resource and will make it an object, not an IRI. (same notes as AbstractComponent).
 *
 * @author Daniel West <daniel@silverback.is>
 */
#[ApiResource]
#[Get]
abstract class AbstractPageData extends AbstractPage implements PageDataInterface
{
    #[Assert\NotBlank(message: 'Please select a page template')]
    #[Groups(['Route:manifest:read'])]
    public Page $page;
}
