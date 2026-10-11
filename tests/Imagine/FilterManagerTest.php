<?php

/*
 * This file is part of the Silverback API Components Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Silverback\ApiComponentsBundle\Tests\Imagine;

use Imagine\Gd\Imagine;
use Liip\ImagineBundle\Binary\SimpleMimeTypeGuesser;
use Liip\ImagineBundle\Imagine\Filter\FilterConfiguration;
use Liip\ImagineBundle\Model\Binary;
use PHPUnit\Framework\TestCase;
use Silverback\ApiComponentsBundle\Imagine\FilterManager;
use Symfony\Component\Mime\MimeTypes;

class FilterManagerTest extends TestCase
{
    public function test_an_avif_source_is_filtered_into_webp(): void
    {
        $filtered = $this->createFilterManager()->applyFilter(new Binary($this->png(), 'image/avif', 'avif'), 'thumbnail');

        self::assertSame('webp', $filtered->getFormat());
        self::assertSame('image/webp', $filtered->getMimeType());
        self::assertSame('image/webp', getimagesizefromstring($filtered->getContent())['mime']);
    }

    public function test_a_png_source_is_filtered_into_png(): void
    {
        $filtered = $this->createFilterManager()->applyFilter(new Binary($this->png(), 'image/png', 'png'), 'thumbnail');

        self::assertSame('png', $filtered->getFormat());
        self::assertSame('image/png', getimagesizefromstring($filtered->getContent())['mime']);
    }

    public function test_a_configured_format_is_kept_for_an_avif_source(): void
    {
        $filtered = $this->createFilterManager()->applyFilter(new Binary($this->png(), 'image/heic', 'heic'), 'jpeg_thumbnail');

        self::assertSame('jpeg', $filtered->getFormat());
        self::assertSame('image/jpeg', getimagesizefromstring($filtered->getContent())['mime']);
    }

    private function createFilterManager(): FilterManager
    {
        return new FilterManager(new FilterConfiguration([
            'thumbnail' => ['filters' => []],
            'jpeg_thumbnail' => ['format' => 'jpeg', 'filters' => []],
        ]), new Imagine(), new SimpleMimeTypeGuesser(MimeTypes::getDefault()));
    }

    private function png(): string
    {
        $image = imagecreatetruecolor(4, 3);
        ob_start();
        imagepng($image);

        return (string) ob_get_clean();
    }
}
