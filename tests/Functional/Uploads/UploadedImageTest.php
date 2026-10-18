<?php

/*
 * This file is part of the Silverback API Components Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Silverback\ApiComponentsBundle\Tests\Functional\Uploads;

use PHPUnit\Framework\Attributes\DataProvider;
use Silverback\ApiComponentsBundle\Tests\Functional\FunctionalTestCase;
use Silverback\ApiComponentsBundle\Tests\Functional\TestBundle\Entity\DummyUploadedImage;
use Silverback\ApiComponentsBundle\Tests\Functional\Trait\AuthenticationTrait;

final class UploadedImageTest extends FunctionalTestCase
{
    use AuthenticationTrait;
    use UploadsAreaTrait;

    private const string TOO_LARGE = 'The image is too large (357500 pixels). Resize it to at most 300000 pixels (about 670 x 447) and upload it again.';

    public function test_an_image_over_the_configured_pixel_limit_is_refused_with_the_limit_and_an_approximate_size(): void
    {
        $client = static::createClient();
        self::loginAsUser($client);

        $this->uploadMultipart($client, '/dummy_uploaded_images/upload', ['file' => 'image.png']);

        self::assertResponseStatusCodeSame(422);
        self::assertJsonNodeSame('violations[0].propertyPath', 'file');
        self::assertJsonNodeSame('violations[0].message', self::TOO_LARGE);
        self::assertJsonNodeNotExists('violations[1]');
    }

    public static function svgAndOtherFiles(): iterable
    {
        yield 'a sized SVG' => ['image.svg', 201];
        yield 'an unsized SVG' => ['unsized.svg', 201];
        yield 'a text file' => ['test_file.txt', 422];
    }

    #[DataProvider('svgAndOtherFiles')]
    public function test_an_svg_skips_the_pixel_checks_and_the_other_file_options_still_apply(string $file, int $status): void
    {
        $client = static::createClient();
        self::loginAsUser($client);

        $this->uploadMultipart($client, '/dummy_uploaded_images/upload', ['file' => $file]);

        self::assertResponseStatusCodeSame($status);
    }

    public function test_the_orphaned_files_report_checks_a_stored_image_against_the_same_configured_limit(): void
    {
        $client = static::createClient();
        self::loginAsAdmin($client);
        $tooManyPixels = self::createUploadable(new DummyUploadedImage());
        self::createUploadable(new DummyUploadedImage(), ['file' => 'unsized.svg']);

        self::requestJsonLd($client, 'POST', '/_/orphaned_files/scan');
        self::requestJsonLd($client, 'GET', '/_/orphaned_files');

        self::assertResponseStatusCodeSame(200);
        self::assertJsonNodeCount('invalidFiles', 1);
        self::assertJsonNodeSame('invalidFiles[0].resource', self::iriOf($tooManyPixels));
        self::assertJsonNodeSame('invalidFiles[0].violations[0]', self::TOO_LARGE);
    }
}
