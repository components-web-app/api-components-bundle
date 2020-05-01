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
use Silverback\ApiComponentsBundle\Flysystem\FilesystemProvider;
use Symfony\Component\Mime\MimeTypesInterface;

/**
 * @author Daniel West <daniel@silverback.is>
 */
class FlysystemDataLoader implements LoaderInterface
{
    protected Filesystem $filesystem;

    protected MimeTypesInterface $extensionGuesser;

    public function __construct(MimeTypesInterface $extensionGuesser, FilesystemProvider $filesystemProvider, string $adapterAlias)
    {
        $this->extensionGuesser = $extensionGuesser;
        $this->filesystem = $filesystemProvider->getFilesystem($adapterAlias);
    }

    /**
     * {@inheritdoc}
     */
    public function find($path)
    {
        if (false === $this->filesystem->has($path)) {
            throw new NotLoadableException(sprintf('Source image "%s" not found.', $path));
        }

        $mimeType = $this->filesystem->getMimetype($path);

        $extension = $this->getExtension($mimeType);

        return new Binary(
            $this->filesystem->read($path),
            $mimeType,
            $extension
        );
    }

    private function getExtension(?string $mimeType): ?string
    {
        if ($this->extensionGuesser instanceof DeprecatedExtensionGuesserInterface) {
            return $this->extensionGuesser->guess($mimeType);
        }

        if (null === $mimeType) {
            return null;
        }

        return $this->extensionGuesser->getExtensions($mimeType)[0] ?? null;
    }
}
