<?php

declare(strict_types=1);

namespace Silverback\ApiComponentBundle\Dto\File;

use Liip\ImagineBundle\Binary\BinaryInterface;
use Silverback\ApiComponentBundle\Exception\FileMissingException;
use Silverback\ApiComponentBundle\Exception\FileNotImageException;
use Symfony\Component\Serializer\Annotation\Groups;

class ImageMetadata
{
    /**
     * @Groups({"default"})
     * @var int
     */
    private $width;

    /**
     * @Groups({"default"})
     * @var int
     */
    private $height;

    /**
     * @Groups({"default"})
     * @var string
     */
    private $filePath;

    /**
     * @Groups({"default"})
     * @var string
     */
    private $publicPath;

    /**
     * @Groups({"default"})
     * @var string|null
     */
    private $imagineKey;

    public function __construct(
        string $filePath,
        string $publicPath,
        ?string $imagineKey = null
    ) {
        $this->filePath = $filePath;
        $this->publicPath = $publicPath;

        if (pathinfo($filePath, PATHINFO_EXTENSION) === 'svg') {
            $xmlget = simplexml_load_string(file_get_contents($filePath));
            $xmlattributes = $xmlget->attributes();
            $this->width = (int) $xmlattributes->width;
            $this->height = (int) $xmlattributes->height;
        } else {
            // this is if we are on external storage e.g. amazon
            $isAbsolute = false !== strpos($publicPath, '://') || strpos($publicPath, '//') === 0;
            // dump($isAbsolute, $filePath, $publicPath);
            [$this->width, $this->height] = getimagesize($isAbsolute ? $publicPath : $filePath);
            $this->imagineKey = $imagineKey;
        }
    }

    /**
     * @return int
     */
    public function getWidth(): int
    {
        return $this->width;
    }

    /**
     * @return int
     */
    public function getHeight(): int
    {
        return $this->height;
    }

    /**
     * @return string
     */
    public function getFilePath(): string
    {
        return $this->filePath;
    }

    /**
     * @return string
     */
    public function getPublicPath(): string
    {
        return $this->publicPath;
    }

    /**
     * @return null|string
     */
    public function getImagineKey(): ?string
    {
        return $this->imagineKey;
    }
}
