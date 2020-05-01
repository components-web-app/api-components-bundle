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

namespace Silverback\ApiComponentsBundle\Model\Uploadable;

use Ramsey\Uuid\Uuid;

/**
 * @author Daniel West <daniel@silverback.is>
 */
final class MediaObject
{
    private string $id;

    public string $contentUrl;

    public int $fileSize;

    public string $mimeType;

    public ?ImageDimensions $dimensions;

    public ?string $imagineFilter = null;

    public function __construct()
    {
        $this->id = Uuid::uuid4()->getHex()->toString();
    }

    public function getId()
    {
        return $this->id;
    }
}
