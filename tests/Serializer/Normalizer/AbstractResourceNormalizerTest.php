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

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Silverback\ApiComponentsBundle\Entity\Core\AbstractComponent;
use Silverback\ApiComponentsBundle\Entity\Core\Page;
use Silverback\ApiComponentsBundle\Exception\InvalidArgumentException;
use Silverback\ApiComponentsBundle\Serializer\Normalizer\AbstractResourceNormalizer;
use Silverback\ApiComponentsBundle\Tests\Functional\TestBundle\Entity\DummyComponent;
use Silverback\ApiComponentsBundle\Utility\ApiResourceRouteFinder;
use Symfony\Component\Routing\Exception\ResourceNotFoundException;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Serializer\Exception\NotNormalizableValueException;
use Symfony\Component\Serializer\Exception\UnexpectedValueException;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;

#[CoversClass(AbstractResourceNormalizer::class)]
class AbstractResourceNormalizerTest extends TestCase
{
    private const string ALREADY_CALLED = 'ABSTRACT_COMPONENT_NORMALIZER_ALREADY_CALLED';

    /** @var list<array{mixed, string, ?string, array<string, mixed>}> */
    private array $calls = [];

    private object $denormalized;

    /**
     * @param array<string, mixed>|null $parameters
     */
    private function buildNormalizer(?array $parameters): AbstractResourceNormalizer
    {
        $router = $this->createStub(RouterInterface::class);
        if (null === $parameters) {
            $router->method('match')->willThrowException(new ResourceNotFoundException('No routes found.', 7));
        } else {
            $router->method('match')->willReturn($parameters);
        }

        $this->denormalized = new \stdClass();
        $normalizer = new AbstractResourceNormalizer(new ApiResourceRouteFinder($router));
        $calls = &$this->calls;
        $denormalized = $this->denormalized;
        $normalizer->setDenormalizer(new class($calls, $denormalized) implements DenormalizerInterface {
            public function __construct(private array &$calls, private object $denormalized)
            {
            }

            public function denormalize(mixed $data, string $type, ?string $format = null, array $context = []): mixed
            {
                $this->calls[] = [$data, $type, $format, $context];

                return $this->denormalized;
            }

            public function supportsDenormalization(mixed $data, string $type, ?string $format = null, array $context = []): bool
            {
                return true;
            }

            public function getSupportedTypes(?string $format): array
            {
                return ['*' => false];
            }
        });

        return $normalizer;
    }

    public function test_an_abstract_type_is_supported(): void
    {
        self::assertTrue($this->buildNormalizer([])->supportsDenormalization('/iri', AbstractComponent::class));
    }

    public function test_a_concrete_type_is_not_supported(): void
    {
        self::assertFalse($this->buildNormalizer([])->supportsDenormalization('/iri', Page::class));
    }

    public function test_an_abstract_type_already_denormalized_once_is_not_supported(): void
    {
        self::assertFalse($this->buildNormalizer([])->supportsDenormalization('/iri', AbstractComponent::class, null, [self::ALREADY_CALLED => true]));
    }

    public function test_a_type_that_is_not_a_class_is_not_supported(): void
    {
        self::assertFalse($this->buildNormalizer([])->supportsDenormalization('/iri', 'NotAClass'));
    }

    public function test_it_supports_any_object_without_caching(): void
    {
        self::assertSame(['object' => false], $this->buildNormalizer([])->getSupportedTypes('jsonld'));
    }

    /**
     * @return iterable<string, array{mixed}>
     */
    public static function iriData(): iterable
    {
        yield 'an IRI' => ['/component/dummy_components/1'];
        yield 'a document with an @id' => [['@id' => '/component/dummy_components/1', 'name' => 'x']];
    }

    #[DataProvider('iriData')]
    public function test_an_iri_is_denormalized_as_the_class_its_route_names(mixed $data): void
    {
        $normalizer = $this->buildNormalizer(['_api_resource_class' => DummyComponent::class]);

        $result = $normalizer->denormalize($data, AbstractComponent::class, 'jsonld', ['groups' => ['a']]);

        self::assertSame($this->denormalized, $result);
        self::assertSame([[$data, DummyComponent::class, 'jsonld', ['groups' => ['a'], self::ALREADY_CALLED => true]]], $this->calls);
    }

    public function test_a_document_without_an_id_is_denormalized_as_the_given_type(): void
    {
        $normalizer = $this->buildNormalizer(null);

        $normalizer->denormalize(['name' => 'x'], AbstractComponent::class, 'jsonld');

        self::assertSame([[['name' => 'x'], AbstractComponent::class, 'jsonld', [self::ALREADY_CALLED => true]]], $this->calls);
    }

    /**
     * @return iterable<string, array{?array<string, mixed>, string}>
     */
    public static function unresolvableRoutes(): iterable
    {
        yield 'no route matches' => [null, 'No route matches "__new__".'];
        yield 'the route is not an API resource' => [['_route' => 'health'], 'No resource associated to "__new__".'];
    }

    /**
     * @param array<string, mixed>|null $parameters
     */
    #[DataProvider('unresolvableRoutes')]
    public function test_an_iri_no_resource_route_matches_is_an_unexpected_value_as_api_platform_reports_an_invalid_iri(?array $parameters, string $previousMessage): void
    {
        $normalizer = $this->buildNormalizer($parameters);

        try {
            $normalizer->denormalize('__new__', AbstractComponent::class, 'jsonld');
            self::fail('No exception was thrown.');
        } catch (UnexpectedValueException $exception) {
            self::assertNotInstanceOf(NotNormalizableValueException::class, $exception);
            self::assertSame('Invalid IRI "__new__".', $exception->getMessage());
            self::assertSame(null === $parameters ? 7 : 0, $exception->getCode());
            self::assertInstanceOf(InvalidArgumentException::class, $exception->getPrevious());
            self::assertSame($previousMessage, $exception->getPrevious()->getMessage());
        }
        self::assertSame([], $this->calls);
    }

    /**
     * @param array<string, mixed>|null $parameters
     */
    #[DataProvider('unresolvableRoutes')]
    public function test_an_iri_no_resource_route_matches_is_a_violation_on_its_path_when_errors_are_collected(?array $parameters, string $previousMessage): void
    {
        $normalizer = $this->buildNormalizer($parameters);

        try {
            $normalizer->denormalize('__new__', AbstractComponent::class, 'jsonld', ['not_normalizable_value_exceptions' => [], 'deserialization_path' => 'component']);
            self::fail('No exception was thrown.');
        } catch (NotNormalizableValueException $exception) {
            self::assertSame('Invalid IRI "__new__".', $exception->getMessage());
            self::assertSame('component', $exception->getPath());
            self::assertSame([AbstractComponent::class], $exception->getExpectedTypes());
            self::assertSame('string', $exception->getCurrentType());
            self::assertTrue($exception->canUseMessageForUser());
            self::assertSame(null === $parameters ? 7 : 0, $exception->getCode());
            self::assertInstanceOf(InvalidArgumentException::class, $exception->getPrevious());
            self::assertSame($previousMessage, $exception->getPrevious()->getMessage());
        }
        self::assertSame([], $this->calls);
    }

    public function test_a_violation_without_a_deserialization_path_has_no_path(): void
    {
        $normalizer = $this->buildNormalizer(null);

        $this->expectException(NotNormalizableValueException::class);
        try {
            $normalizer->denormalize('__new__', AbstractComponent::class, 'jsonld', ['not_normalizable_value_exceptions' => []]);
        } catch (NotNormalizableValueException $exception) {
            self::assertNull($exception->getPath());

            throw $exception;
        }
    }
}
