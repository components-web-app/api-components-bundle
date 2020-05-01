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

use League\Flysystem\Filesystem;
use Liip\ImagineBundle\Binary\Loader\LoaderInterface;
use Liip\ImagineBundle\Exception\Binary\Loader\NotLoadableException;
use Liip\ImagineBundle\Model\Binary;
use Symfony\Component\Mime\MimeTypes;

/**
 * @author Daniel West <daniel@silverback.is>
 */
class FlysystemDataLoader implements LoaderInterface
{
    protected Filesystem $filesystem;

    public function __construct(Filesystem $filesystem)
    {
        $this->filesystem = $filesystem;
    }

    /**
     * {@inheritdoc}
     */
    public function find($path)
    {
        if (false === $this->filesystem->fileExists($path)) {
            throw new NotLoadableException(sprintf('Source image "%s" not found.', $path));
        }

        $mimeType = $this->filesystem->mimeType($path);

        $extension = MimeTypes::getDefault()->getExtensions($mimeType)[0];

        return new Binary(
            $this->filesystem->read($path),
            $mimeType,
            $extension
        );
    }
}
