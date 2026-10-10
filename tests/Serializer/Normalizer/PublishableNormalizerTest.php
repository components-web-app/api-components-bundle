<?php

/*
 * This file is part of the Silverback API Components Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Silverback\ApiComponentsBundle\Tests\Serializer\Normalizer;

use ApiPlatform\Metadata\IriConverterInterface;
use ApiPlatform\Validator\ValidatorInterface;
use Doctrine\Persistence\ManagerRegistry;
use Doctrine\Persistence\ObjectManager;
use PHPUnit\Framework\TestCase;
use Silverback\ApiComponentsBundle\Annotation\Publishable;
use Silverback\ApiComponentsBundle\AttributeReader\PublishableAttributeReader;
use Silverback\ApiComponentsBundle\Exception\InvalidArgumentException;
use Silverback\ApiComponentsBundle\Helper\Publishable\PublishableStatusChecker;
use Silverback\ApiComponentsBundle\Helper\Uploadable\UploadableFileManager;
use Silverback\ApiComponentsBundle\Metadata\Provider\PageDataMetadataProvider;
use Silverback\ApiComponentsBundle\Serializer\Normalizer\PublishableNormalizer;
use Silverback\ApiComponentsBundle\Serializer\ResourceMetadata\ResourceMetadataProvider;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;

#[Publishable]
class _PublishNowStub
{
    public ?\DateTimeInterface $publishedAt = null;
}

class PublishableNormalizerTest extends TestCase
{
    public function test_a_publication_date_of_now_reaches_the_denormalizer_as_the_servers_current_time(): void
    {
        $before = new \DateTimeImmutable();
        $data = $this->denormalizedData(['publishedAt' => 'now', 'reference' => 'r']);
        $after = new \DateTimeImmutable();

        self::assertSame('r', $data['reference']);
        self::assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}\.\d{3}[+-]\d{2}:\d{2}$/', $data['publishedAt']);
        $publishedAt = new \DateTimeImmutable($data['publishedAt']);
        self::assertGreaterThanOrEqual($before->format('Uv'), $publishedAt->format('Uv'));
        self::assertLessThanOrEqual($after->format('Uv'), $publishedAt->format('Uv'));
    }

    public function test_only_the_exact_string_now_is_resolved(): void
    {
        foreach (['Now', ' now', 'now ', 'tomorrow', '2999-12-31T23:59:59+00:00', null] as $value) {
            self::assertSame(['publishedAt' => $value], $this->denormalizedData(['publishedAt' => $value]), var_export($value, true));
        }
        self::assertSame(['reference' => 'r'], $this->denormalizedData(['reference' => 'r']));
    }

    private function denormalizedData(array $data): array
    {
        $statusChecker = $this->createStub(PublishableStatusChecker::class);
        $statusChecker->method('getAttributeReader')->willReturn(new PublishableAttributeReader($this->createStub(ManagerRegistry::class)));
        $statusChecker->method('isGranted')->willReturn(true);

        $received = null;
        $inner = $this->createStub(DenormalizerInterface::class);
        $inner->method('denormalize')->willReturnCallback(static function (mixed $data) use (&$received): \stdClass {
            $received = $data;

            return new \stdClass();
        });

        $normalizer = $this->createNormalizer($this->createStub(ManagerRegistry::class), $statusChecker);
        $normalizer->setDenormalizer($inner);
        $normalizer->denormalize($data, _PublishNowStub::class);

        return $received;
    }

    public function test_creating_a_draft_fails_clearly_when_the_class_is_not_managed_by_the_orm(): void
    {
        $manager = $this->createMock(ObjectManager::class);
        $manager->expects(self::never())->method('persist');
        $registry = $this->createStub(ManagerRegistry::class);
        $registry->method('getManagerForClass')->willReturn($manager);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage(\sprintf('Could not find entity manager for class %s', \stdClass::class));
        $this->createNormalizer($registry)->createDraft(new \stdClass(), new Publishable(), \stdClass::class);
    }

    public function test_creating_a_draft_fails_clearly_when_the_class_has_no_manager(): void
    {
        $registry = $this->createStub(ManagerRegistry::class);
        $registry->method('getManagerForClass')->willReturn(null);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage(\sprintf('Could not find entity manager for class %s', \stdClass::class));
        $this->createNormalizer($registry)->createDraft(new \stdClass(), new Publishable(), \stdClass::class);
    }

    private function createNormalizer(ManagerRegistry $registry, ?PublishableStatusChecker $statusChecker = null): PublishableNormalizer
    {
        return new PublishableNormalizer(
            $statusChecker ?? $this->createStub(PublishableStatusChecker::class),
            $registry,
            new RequestStack(),
            $this->createStub(ValidatorInterface::class),
            $this->createStub(IriConverterInterface::class),
            $this->createStub(UploadableFileManager::class),
            $this->createStub(ResourceMetadataProvider::class),
            $this->createStub(EventDispatcherInterface::class),
            $this->createStub(PageDataMetadataProvider::class),
        );
    }
}
