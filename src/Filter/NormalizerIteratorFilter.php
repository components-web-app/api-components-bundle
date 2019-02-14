<?php

namespace Silverback\ApiComponentBundle\Filter;

use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

class NormalizerIteratorFilter extends \FilterIterator
{
    public function accept(): bool
    {
        $normalizer = $this->getInnerIterator()->current();
        return $normalizer instanceof NormalizerInterface;
    }
}
