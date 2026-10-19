<?php

/*
 * This file is part of the Silverback API Components Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Silverback\ApiComponentsBundle\Model\Component;

use Symfony\Component\Serializer\Attribute\Groups;

final class ComponentMove
{
    public function __construct(
        #[Groups(['ComponentMove:read', 'ComponentMove:write'])]
        public mixed $from = null,
        #[Groups(['ComponentMove:read', 'ComponentMove:write'])]
        public mixed $to = null,
        #[Groups(['ComponentMove:read'])]
        public ?string $componentPosition = null,
    ) {
    }
}
