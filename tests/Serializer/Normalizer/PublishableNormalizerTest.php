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
use Silverback\ApiComponentsBundle\Exception\InvalidArgumentException;
use Silverback\ApiComponentsBundle\Helper\Publishable\PublishableStatusChecker;
use Silverback\ApiComponentsBundle\Helper\Uploadable\UploadableFileManager;
use Silverback\ApiComponentsBundle\Metadata\Provider\PageDataMetadataProvider;
use Silverback\ApiComponentsBundle\Serializer\Normalizer\PublishableNormalizer;
use Silverback\ApiComponentsBundle\Serializer\ResourceMetadata\ResourceMetadataProvider;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\RequestStack;

class PublishableNormalizerTest extends TestCase
{
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

    private function createNormalizer(ManagerRegistry $registry): PublishableNormalizer
    {
        return new PublishableNormalizer(
            $this->createStub(PublishableStatusChecker::class),
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
