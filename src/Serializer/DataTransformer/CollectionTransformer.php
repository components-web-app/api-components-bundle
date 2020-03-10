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

namespace Silverback\ApiComponentBundle\Serializer\DataTransformer;

use Doctrine\Common\Collections\ArrayCollection;
use Silverback\ApiComponentBundle\Entity\Component\Collection;

/**
 * @author Daniel West <daniel@silverback.is>
 */
class CollectionTransformer
{
    public function transform(Collection $collection): Collection
    {
        $collection->setCollection(new ArrayCollection(['something testing the transformer']));

        return $collection;
    }
}
