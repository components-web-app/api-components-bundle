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

namespace Silverback\ApiComponentsBundle\Model\Uploadable;

use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\Mime\MimeTypes;

/**
 * Based on https://github.com/hshn/base64-encoded-file/blob/master/src/HttpFoundation/File/Base64EncodedFile.php
 * Additional support for data uri - regular expression detection.
 *
 * @author Daniel West <daniel@silverback.is>
 */
class DataUriFile extends File
{
    /**
     * @param string $encoded
     * @param bool   $checkPath
     */
    public function __construct($encoded, $checkPath = true)
    {
        parent::__construct($this->restoreToTemporary($encoded), $checkPath);
    }

    /**
     * @param string $encoded
     *
     * @throws FileException
     */
    private function restoreToTemporary($encoded): string
    {
        preg_match('/^(?:(?:data:(?:\/\/)?([A-Za-z]+\/[A-Za-z.\-\+]+)(?:;(base64))?,|)(?:(.+)|((?:[A-Za-z0-9+\/]{4})*(?:[A-Za-z0-9+\/]{2}==|[A-Za-z0-9+\/]{3}=)?)))$/', $encoded, $matches);

        $mimeType = $matches[1];
        $base64Match = 'base64' === $matches[2];
        $isBase64 = '' === $mimeType || $base64Match;
        if ($isBase64 && !$mimeType) {
            return $this->decodePlainBase64($encoded);
        }
        if (!$isBase64) {
            return $this->decodeDataUri($matches[3], $mimeType);
        }

        return $this->decodeBase64Data($mimeType, $encoded);
    }

    private function decodePlainBase64(string $encoded): string
    {
        if (false === $decoded = base64_decode($encoded, true)) {
            throw new FileException('Unable to decode the string as plain base64');
        }

        $path = $this->getTempFileBasePath();

        if (false === file_put_contents($path, $decoded, \FILE_BINARY)) {
            throw new FileException(sprintf('Unable to write the file "%s"', $path));
        }

        return $path;
    }

    private function decodeDataUri(string $dataUriMatch, string $mimeType): string
    {
        $target = $this->createTempFileTarget($mimeType);
        $content = urldecode($dataUriMatch);
        if (false === @fwrite($target->resource, $content)) {
            throw new FileException(sprintf('Unable to write the file "%s"', $target->path));
        }
        $this->closeFile($target);

        return $target->path;
    }

    private function decodeBase64Data(string $mimeType, string $fullMatch): string
    {
        $target = $this->createTempFileTarget($mimeType);

        $source = @fopen($fullMatch, 'r');
        if (false === $source) {
            throw new FileException('Unable to decode strings as base64');
        }

        if (false === stream_copy_to_stream($source, $target->resource)) {
            throw new FileException(sprintf('Unable to write the file "%s"', $target->path));
        }

        if (false === @fclose($source)) {
            throw new FileException('Unable to close data stream');
        }

        $this->closeFile($target);

        return $target->path;
    }

    private function getTempFileBasePath(): string
    {
        if (false === $path = tempnam($directory = sys_get_temp_dir(), 'DataUriFile')) {
            throw new FileException(sprintf('Unable to create a file into the "%s" directory: %s', $directory, $path));
        }

        return $path;
    }

    private function createTempFileTarget(string $mimeType): object
    {
        $path = $this->getTempFileBasePath();

        if (null !== $extension = (MimeTypes::getDefault()->getExtensions($mimeType)[0] ?? null)) {
            $path .= '.' . $extension;
        }

        if (false === $target = @fopen($path, 'wb+')) {
            throw new FileException(sprintf('Unable to open the file "%s"', $path));
        }

        $class = new class() {
            public string $path;
            public $resource;
        };
        $class->path = $path;
        $class->resource = $target;

        return $class;
    }

    private function closeFile(object $target): void
    {
        if (false === @fclose($target->resource)) {
            throw new FileException(sprintf('Unable to close the file "%s"', $target->path));
        }
    }
}
