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
use Silverback\ApiComponentsBundle\Model\Uploadable\UploadedDataUriFile;
use Symfony\Component\Validator\Constraints\Image;
use Symfony\Component\Validator\Validation;

class UploadedDataUriFileTest extends TestCase
{
    private const string ASSETS = __DIR__ . '/../../../features/assets/files/';

    public function test_a_decoded_data_uri_is_a_valid_upload_with_its_client_name_and_mime_type(): void
    {
        $file = new UploadedDataUriFile($this->dataUri('image.png'), 'photo.png');

        self::assertTrue($file->isValid());
        self::assertSame(\UPLOAD_ERR_OK, $file->getError());
        self::assertSame('photo.png', $file->getClientOriginalName());
        self::assertSame('image/png', $file->getMimeType());
    }

    public function test_the_client_name_defaults_to_the_decoded_files_name(): void
    {
        $dataUri = $this->dataUri('image.png');

        self::assertSame($dataUri->getFilename(), (new UploadedDataUriFile($dataUri))->getClientOriginalName());
    }

    public function test_a_file_constraint_validates_its_content_rather_than_refusing_it_as_a_failed_upload(): void
    {
        $violations = Validation::createValidator()->validate(new UploadedDataUriFile($this->dataUri('image.png'), 'photo.png'), new Image(maxPixels: 300_000));

        self::assertCount(1, $violations);
        self::assertSame(Image::TOO_MANY_PIXEL_ERROR, $violations[0]->getCode());
    }

    public function test_it_can_be_moved(): void
    {
        $directory = sys_get_temp_dir() . '/acb-' . bin2hex(random_bytes(4));

        try {
            $moved = (new UploadedDataUriFile($this->dataUri('image.png'), 'photo.png'))->move($directory, 'moved.png');

            self::assertFileEquals(self::ASSETS . 'image.png', $moved->getPathname());
        } finally {
            @unlink($directory . '/moved.png');
            @rmdir($directory);
        }
    }

    private function dataUri(string $asset): DataUriFile
    {
        return new DataUriFile('data:image/png;base64,' . base64_encode((string) file_get_contents(self::ASSETS . $asset)));
    }
}
