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

namespace Silverback\ApiComponentBundle\Annotation;

use League\Flysystem\Filesystem;
use Silverback\ApiComponentBundle\Exception\UnsupportedAnnotationException;

/**
 * @author Daniel West <daniel@silverback.is>
 *
 * @Annotation
 * @Target("CLASS")
 */
final class File
{
    public string $fileFieldName = 'file';

    public string $filePathFieldName = 'filePath';

    public string $mediaObjectsProperty = 'mediaObjects';

    public string $uploadsFieldName = 'uploadsResource';

    /** @required */
    public ?string $uploadsEntityClass;

    public function __construct(array $values)
    {
        if (!class_exists(Filesystem::class)) {
            throw new UnsupportedAnnotationException(sprintf('%s does not exist. Please install FlySystem v2 to use the @Silverback\Files annotation', Filesystem::class));
        }
        if (isset($values['value'])) {
            $this->uploadsEntityClass = $values['value'];
        }
    }
}
