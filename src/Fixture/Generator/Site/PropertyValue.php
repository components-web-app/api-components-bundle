<?php

/*
 * This file is part of the Silverback API Components Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Silverback\ApiComponentsBundle\Fixture\Generator\Site;

/**
 * @internal
 */
final readonly class PropertyValue
{
    public const string FIELD = 'field';
    public const string FILE = 'file';
    public const string COMPONENT = 'component';
    public const string ROUTE = 'route';
    public const string ENTITY = 'entity';
    public const string COLLECTION = 'collection';

    public function __construct(
        public string $kind,
        public string $property,
        public mixed $value,
        public bool $writable,
        public ?string $setter = null,
    ) {
    }
}
