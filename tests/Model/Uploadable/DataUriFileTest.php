<?php

/*
 * This file is part of the Silverback API Components Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Silverback\ApiComponentsBundle\Tests\Model\Uploadable;

use PHPUnit\Framework\TestCase;
use Silverback\ApiComponentsBundle\Model\Uploadable\DataUriFile;

class DataUriFileTest extends TestCase
{
    private const string IMAGE = __DIR__ . '/../../Functional/assets/files/image.png';

    public function test_a_base64_data_uri_is_decoded_to_a_temporary_file_named_with_its_mime_types_extension(): void
    {
        $file = new DataUriFile('data:image/png;base64,' . base64_encode((string) file_get_contents(self::IMAGE)));

        try {
            self::assertSame(realpath(sys_get_temp_dir()), realpath($file->getPath()));
            self::assertMatchesRegularExpression('/^DataUriFile\w+\.png$/', $file->getFilename());
            self::assertFileEquals(self::IMAGE, $file->getPathname());
        } finally {
            @unlink($file->getPathname());
        }
    }

    public function test_a_url_encoded_data_uri_is_decoded_to_a_temporary_file_named_with_its_mime_types_extension(): void
    {
        $file = new DataUriFile('data:text/plain,' . rawurlencode('Hello, world'));

        try {
            self::assertSame(realpath(sys_get_temp_dir()), realpath($file->getPath()));
            self::assertMatchesRegularExpression('/^DataUriFile\w+\.txt$/', $file->getFilename());
            self::assertStringEqualsFile($file->getPathname(), 'Hello, world');
        } finally {
            @unlink($file->getPathname());
        }
    }
}
