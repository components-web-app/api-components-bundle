<?php

/*
 * This file is part of the Silverback API Components Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Silverback\ApiComponentsBundle\Tests\Factory\Uploadable;

use League\Flysystem\Filesystem;
use PHPUnit\Framework\TestCase;
use Silverback\ApiComponentsBundle\Factory\Uploadable\TemporaryUrlGenerator;

class TemporaryUrlGeneratorTest extends TestCase
{
    public function test_generates_temporary_url_from_filesystem(): void
    {
        $filesystem = $this->createMock(Filesystem::class);
        $filesystem->method('temporaryUrl')
            ->willReturn('https://s3.example.com/file.png?signed=abc');

        $generator = new TemporaryUrlGenerator();

        $result = $generator->generateUrl(new \stdClass(), 'file', $filesystem, '/uploads/file.png');

        $this->assertSame('https://s3.example.com/file.png?signed=abc', $result);
    }

    public function test_uses_configured_expiry_string(): void
    {
        $capturedExpiry = null;
        $filesystem = $this->createMock(Filesystem::class);
        $filesystem->method('temporaryUrl')
            ->willReturnCallback(static function (string $path, \DateTimeInterface $expiry, array $config) use (&$capturedExpiry): string {
                $capturedExpiry = $expiry;

                return 'https://s3.example.com/signed';
            });

        $generator = new TemporaryUrlGenerator(expires: '+1 hour');
        $before = new \DateTime();
        $generator->generateUrl(new \stdClass(), 'file', $filesystem, 'file.png');
        $after = new \DateTime('+1 hour');

        $this->assertGreaterThan($before->getTimestamp(), $capturedExpiry->getTimestamp());
        $this->assertLessThanOrEqual($after->getTimestamp(), $capturedExpiry->getTimestamp());
    }

    public function test_passes_config_to_filesystem(): void
    {
        $config = ['ServerSideEncryption' => 'AES256'];

        $capturedConfig = null;
        $filesystem = $this->createMock(Filesystem::class);
        $filesystem->method('temporaryUrl')
            ->willReturnCallback(static function (string $path, \DateTimeInterface $expiry, array $config) use (&$capturedConfig): string {
                $capturedConfig = $config;

                return 'https://s3.example.com/signed';
            });

        $generator = new TemporaryUrlGenerator($config);
        $generator->generateUrl(new \stdClass(), 'file', $filesystem, 'file.png');

        $this->assertSame($config, $capturedConfig);
    }
}
