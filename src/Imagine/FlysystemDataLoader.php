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

namespace Silverback\ApiComponentsBundle\Imagine;

use Liip\ImagineBundle\Binary\Loader\LoaderInterface;
use Liip\ImagineBundle\Exception\Binary\Loader\NotLoadableException;
use Liip\ImagineBundle\Model\Binary;
use Silverback\ApiComponentsBundle\Flysystem\FilesystemProvider;
use Symfony\Component\Mime\MimeTypes;

/**
 * @author Daniel West <daniel@silverback.is>
 */
class FlysystemDataLoader implements LoaderInterface
{
    protected FilesystemProvider $filesystemProvider;
    private ?string $adapter = null;

    public function __construct(FilesystemProvider $filesystemProvider)
    {
        $this->filesystemProvider = $filesystemProvider;
    }

    public function setAdapter(?string $adapter): void
    {
        $this->adapter = $adapter;
    }

    /**
     * {@inheritdoc}
     */
    public function find($path)
    {
        $filesystem = $this->filesystemProvider->getFilesystem($this->adapter);

        // This should be finding the file that we have uploaded into a location already - source file locator
        if (false === $filesystem->fileExists($path)) {
            throw new NotLoadableException(sprintf('Source image "%s" not found.', $path));
        }

        $mimeType = $filesystem->mimeType($path);

        $extension = MimeTypes::getDefault()->getExtensions($mimeType)[0];

        return new Binary(
            $filesystem->read($path),
            $mimeType,
            $extension
        );
    }
}
