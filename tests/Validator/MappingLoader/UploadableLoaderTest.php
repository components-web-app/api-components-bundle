<?php

/*
 * This file is part of the Silverback API Components Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Silverback\ApiComponentsBundle\Tests\Validator\MappingLoader;

use Doctrine\Persistence\ManagerRegistry;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Silverback\ApiComponentsBundle\AttributeReader\AttributeReader;
use Silverback\ApiComponentsBundle\AttributeReader\PublishableAttributeReader;
use Silverback\ApiComponentsBundle\AttributeReader\UploadableAttributeReader;
use Silverback\ApiComponentsBundle\Tests\Functional\TestBundle\Entity\DummyUploadable;
use Silverback\ApiComponentsBundle\Tests\Functional\TestBundle\Entity\DummyUploadableRequiredOnPublish;
use Silverback\ApiComponentsBundle\Tests\Functional\TestBundle\Entity\DummyUploadableRequiredOnPublishCustomGroup;
use Silverback\ApiComponentsBundle\Validator\Constraints\RequiresUploadedFile;
use Silverback\ApiComponentsBundle\Validator\MappingLoader\UploadableLoader;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\PhpFileLoader;
use Symfony\Component\Validator\Mapping\ClassMetadata;

class UploadableLoaderTest extends TestCase
{
    private const SERVICE_ID = 'silverback.api_components.validator.mapping_loader.uploadable';

    private function getLoaderFromContainer(): UploadableLoader
    {
        $source = new ContainerBuilder();
        (new PhpFileLoader($source, new FileLocator(\dirname(__DIR__, 3) . '/src/Resources/config')))->load('services.php');

        $container = new ContainerBuilder();
        $container->register('doctrine', ManagerRegistry::class)->setSynthetic(true);
        foreach ([
            AttributeReader::class,
            'silverback.api_components.attribute_reader.uploadable',
            'silverback.api_components.attribute_reader.publishable',
            self::SERVICE_ID,
        ] as $id) {
            $container->setDefinition($id, $source->getDefinition($id));
        }
        foreach ([UploadableAttributeReader::class, PublishableAttributeReader::class] as $alias) {
            $container->setAlias($alias, $source->getAlias($alias));
        }
        $container->getDefinition('silverback.api_components.attribute_reader.uploadable')->setArgument('$imagineBundleEnabled', false);
        $container->getDefinition(self::SERVICE_ID)->setPublic(true);
        $container->compile();
        $container->set('doctrine', $this->createStub(ManagerRegistry::class));

        return $container->get(self::SERVICE_ID);
    }

    public static function groupsProvider(): iterable
    {
        yield 'default publishable groups' => [DummyUploadableRequiredOnPublish::class, ['DummyUploadableRequiredOnPublish:published']];
        yield 'custom publishable validation groups' => [DummyUploadableRequiredOnPublishCustomGroup::class, ['custom_publish_group']];
    }

    #[DataProvider('groupsProvider')]
    public function test_required_file_constraint_is_in_the_groups_publishing_validates(string $class, array $expectedGroups): void
    {
        $metadata = new ClassMetadata($class);

        self::assertTrue($this->getLoaderFromContainer()->loadClassMetadata($metadata));

        $constraints = array_values(array_filter($metadata->getConstraints(), static fn ($c) => $c instanceof RequiresUploadedFile));
        self::assertNotEmpty($constraints);
        foreach ($constraints as $constraint) {
            self::assertSame($expectedGroups, $constraint->groups);
        }
    }

    public function test_uploadable_without_required_on_publish_gets_no_constraint(): void
    {
        $metadata = new ClassMetadata(DummyUploadable::class);

        self::assertFalse($this->getLoaderFromContainer()->loadClassMetadata($metadata));
        self::assertSame([], $metadata->getConstraints());
    }
}
