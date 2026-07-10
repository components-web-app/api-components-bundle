<?php

/*
 * This file is part of the Silverback API Components Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Silverback\ApiComponentsBundle\Validator\Constraints;

use Symfony\Component\Validator\Constraint;

/**
 * Requires an uploadable field to have a file present (either the transient uploaded file or a
 * previously stored filename). Added programmatically per `#[UploadableField(requiredOnPublish: true)]`
 * in the `{ShortName}:published` group, so multiple fields are validated independently.
 *
 * @author Daniel West <daniel@silverback.is>
 */
#[\Attribute(\Attribute::TARGET_CLASS | \Attribute::IS_REPEATABLE)]
class RequiresUploadedFile extends Constraint
{
    public string $message = 'A file must be uploaded for the `{{ property }}` field before publishing.';

    /**
     * The transient uploaded-file property (e.g. `file`).
     */
    public string $fileProperty = '';

    /**
     * The stored-filename property the upload is persisted to (the UploadableField `property`).
     */
    public string $filenameProperty = 'filename';

    public function getTargets(): string|array
    {
        return self::CLASS_CONSTRAINT;
    }
}
