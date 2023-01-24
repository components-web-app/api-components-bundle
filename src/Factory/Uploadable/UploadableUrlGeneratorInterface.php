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

namespace Silverback\ApiComponentsBundle\Factory\Uploadable;

interface UploadableUrlGeneratorInterface
{
    public const TAG = 'silveback.api_components.uploadable.url_generator';

    public function generateUrl(object $object, string $fileProperty): string;
}
