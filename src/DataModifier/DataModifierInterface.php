<?php

namespace Silverback\ApiComponentBundle\DataModifier;

interface DataModifierInterface
{
    /**
     * Modifies an object with additional data where a service is required to populate the data
     *
     * @param mixed $object Object to normalize
     * @param array $context Context options for the normalizer
     * @param null|string $format
     * @return object|void
     */
    public function process($object, array $context = array(), ?string $format = null);

    /**
     * Checks whether the given class is supported for modifying by the modifier
     *
     * @param mixed  $data   Data to modify
     *
     * @return bool
     */
    public function supportsData($data): bool;
}
