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

namespace Silverback\ApiComponentBundle\Action;

use Silverback\ApiComponentBundle\File\FileRequestHandler;
use Symfony\Component\HttpFoundation\Request;

class FileAction extends AbstractAction
{
    private FileRequestHandler $fileRequestHandler;

    public function __construct(FileRequestHandler $fileRequestHandler)
    {
        $this->fileRequestHandler = $fileRequestHandler;
    }

    public function __invoke(Request $request, string $field, string $id)
    {
        return $this->fileRequestHandler->handle($request, self::getRequestFormat($request), $field, $id);
    }
}
