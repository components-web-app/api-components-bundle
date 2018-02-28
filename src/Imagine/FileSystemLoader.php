<?php

namespace Silverback\ApiComponentBundle\Imagine;

use Liip\ImagineBundle\Binary\Loader\FileSystemLoader as BaseFileSystemLoader;
use Liip\ImagineBundle\Binary\Locator\LocatorInterface;
use Symfony\Component\HttpFoundation\File\MimeType\ExtensionGuesserInterface;
use Symfony\Component\HttpFoundation\File\MimeType\MimeTypeGuesserInterface;

class FileSystemLoader extends BaseFileSystemLoader
{
    /**
     * @var string[]
     */
    private $rootPaths;

    public function __construct(
        MimeTypeGuesserInterface $mimeGuesser,
        ExtensionGuesserInterface $extensionGuesser,
        LocatorInterface $locator,
        array $rootPaths = []
    )
    {
        parent::__construct($mimeGuesser, $extensionGuesser, $locator, $rootPaths);
        $this->rootPaths = $rootPaths;
    }

    /**
     * Strips root directory from the start of a file path
     * @param null|string $filePath
     * @return null|string
     */
    public function getImaginePath(?string $filePath = null): ?string
    {
        if (!$filePath) {
            return $filePath;
        }
        foreach ($this->rootPaths as $rootPath) {
            if (strpos($filePath, $rootPath) === 0) {
                return substr($filePath, \strlen($rootPath));
            }
        }
        return $filePath;
    }
}
