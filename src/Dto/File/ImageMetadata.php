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
     */
    private $width = null;

    /**
     * @Groups({"default"})
     */
    private $height = null;

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
            $hostname = parse_url($publicPath, PHP_URL_HOST);
            if ($hostname === 'localhost') {
                $isAbsolute = false;
            } else {
                $isAbsolute = false !== strpos($publicPath, '://') || strpos($publicPath, '//') === 0;
            }
            [$this->width, $this->height] = getimagesize($isAbsolute ? $publicPath : $filePath);
            $this->imagineKey = $imagineKey;
        }
    }

    public function getWidth(): ?int
    {
        return $this->width;
    }

    public function getHeight(): ?int
    {
        return $this->height;
    }

    public function getFilePath(): string
    {
        return $this->filePath;
    }

    public function getPublicPath(): string
    {
        return $this->publicPath;
    }

    public function getImagineKey(): ?string
    {
        return $this->imagineKey;
    }
}
