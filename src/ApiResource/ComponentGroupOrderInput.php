<?php

/*
 * This file is part of the Silverback API Components Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Silverback\ApiComponentsBundle\ApiResource;

use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;

final class ComponentGroupOrderInput
{
    /**
     * @var array<string>
     */
    #[Groups(['ComponentGroupOrder:write'])]
    #[Assert\All([new Assert\Type('string')])]
    public array $componentPositions = [];
}
