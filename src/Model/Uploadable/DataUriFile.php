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
     * @param bool   $strict
     * @param bool   $checkPath
     */
    public function __construct($encoded, $strict = true, $checkPath = true)
    {
        parent::__construct($this->restoreToTemporary($encoded, $strict), $checkPath);
    }

    /**
     * @param string $encoded
     * @param bool   $strict
     *
     * @throws FileException
     */
    private function restoreToTemporary($encoded, $strict = true): string
    {
        preg_match('/^(?:(?:data:(?:\/\/)?([A-Za-z]+\/[A-Za-z.-]+)(?:;(base64))?,|)(?:(.+)|((?:[A-Za-z0-9+\/]{4})*(?:[A-Za-z0-9+\/]{2}==|[A-Za-z0-9+\/]{3}=)?)))$/', $encoded, $matches);

        $mimeType = $matches[1];
        $base64Match = 'base64' === $matches[2];
        $base64 = '' === $mimeType || $base64Match;
        if ($base64 && !$mimeType) {
            if (false === $decoded = base64_decode($encoded, $strict)) {
                throw new FileException('Unable to decode strings as base64');
            }

            if (false === $path = tempnam($directory = sys_get_temp_dir(), 'DataUriFile')) {
                throw new FileException(sprintf('Unable to create a file into the "%s" directory', $directory));
            }

            if (false === file_put_contents($path, $decoded, FILE_BINARY)) {
                throw new FileException(sprintf('Unable to write the file "%s"', $path));
            }

            return $path;
        }

        if (false === $path = tempnam($directory = sys_get_temp_dir(), 'Base64EncodedFile')) {
            throw new FileException(sprintf('Unable to create a file into the "%s" directory', $path));
        }
        if (null !== $extension = (MimeTypes::getDefault()->getExtensions($mimeType)[0] ?? null)) {
            $path .= '.' . $extension;
        }
        if (false === $target = @fopen($path, 'wb+')) {
            throw new FileException(sprintf('Unable to open the file "%s"', $path));
        }

        if (!$base64) {
            // data uri
            $content = urldecode($matches[3]);
            if (false === @fwrite($target, $content)) {
                throw new FileException(sprintf('Unable to write the file "%s"', $path));
            }
        } else {
            $source = @fopen($encoded, 'r');
            if (false === $source) {
                throw new FileException('Unable to decode strings as base64');
            }

            $meta = stream_get_meta_data($source);
            if ($strict && (!isset($meta['base64']) || true !== $meta['base64'])) {
                throw new FileException('Unable to decode strings as base64');
            }

            if (false === @stream_copy_to_stream($source, $target)) {
                throw new FileException(sprintf('Unable to write the file "%s"', $path));
            }

            if (false === @fclose($source)) {
                throw new FileException(sprintf('Unable to close data stream'));
            }
        }

        if (false === @fclose($target)) {
            throw new FileException(sprintf('Unable to close the file "%s"', $path));
        }

        return $path;
    }
}
