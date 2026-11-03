<?php

/*
 * This file is part of the Silverback API Components Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Silverback\ApiComponentsBundle\Tests\EventListener\Api;

use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\Validator\ValidatorInterface;
use PHPUnit\Framework\TestCase;
use Silverback\ApiComponentsBundle\AttributeReader\PublishableAttributeReader;
use Silverback\ApiComponentsBundle\Entity\Core\Layout;
use Silverback\ApiComponentsBundle\EventListener\Api\PublishableEventListener;
use Silverback\ApiComponentsBundle\Helper\Publishable\PublishableStatusChecker;
use Silverback\ApiComponentsBundle\Tests\Functional\TestBundle\Entity\DummyPublishableComponent;
use Silverback\ApiComponentsBundle\Tests\Helper\Publishable\PublishableDatabaseTrait;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

final class PublishableEventListenerTest extends TestCase
{
    use PublishableDatabaseTrait;

    private const string PUBLISHED_AT = '2999-01-01 00:00:00 UTC';

    /** @var list<string> */
    private array $checked = [];

    protected function setUp(): void
    {
        $this->setUpPublishableDatabase();
        $this->checked = [];
    }

    public function test_a_response_whose_data_is_not_an_object_and_has_no_resource_class_is_left_unchanged(): void
    {
        $response = $this->dispatch(['@context' => ['@vocab' => 'http://example.com/docs.jsonld#']], new Get());

        self::assertSame(['cache-control' => ['no-cache, private']], $this->headersWithoutDate($response));
        self::assertSame([], $this->checked);
    }

    public function test_a_resource_whose_class_is_not_publishable_is_left_unchanged(): void
    {
        $response = $this->dispatch(new Layout(), new Get());

        self::assertSame(['cache-control' => ['no-cache, private']], $this->headersWithoutDate($response));
        self::assertSame([], $this->checked);
    }

    public function test_a_publishable_resource_from_a_collection_operation_is_left_unchanged(): void
    {
        $response = $this->dispatch($this->scheduledComponent(), new GetCollection());

        self::assertSame(['cache-control' => ['no-cache, private']], $this->headersWithoutDate($response));
        self::assertSame([], $this->checked);
    }

    public function test_a_scheduled_publishable_resource_from_an_item_operation_expires_at_its_publication_date(): void
    {
        $response = $this->dispatch($this->scheduledComponent(), new Get());

        self::assertSame([
            'cache-control' => ['private, must-revalidate'],
            'expires' => ['Tue, 01 Jan 2999 00:00:00 GMT'],
        ], $this->headersWithoutDate($response));
        self::assertSame(["is_granted('ROLE_ADMIN')"], $this->checked);
    }

    private function scheduledComponent(): DummyPublishableComponent
    {
        $component = new DummyPublishableComponent();
        $component->setPublishedAt(new \DateTime(self::PUBLISHED_AT));

        return $component;
    }

    private function dispatch(mixed $data, Operation $operation): Response
    {
        $authorizationChecker = $this->createStub(AuthorizationCheckerInterface::class);
        $authorizationChecker->method('isGranted')->willReturnCallback(function (mixed $attribute): bool {
            $this->checked[] = (string) $attribute;

            return false;
        });
        $validator = $this->createMock(ValidatorInterface::class);
        $validator->expects(self::never())->method('validate');
        $listener = new PublishableEventListener(
            new PublishableStatusChecker($this->registry, new PublishableAttributeReader($this->registry), $authorizationChecker, "is_granted('ROLE_ADMIN')"),
            $this->registry,
            $validator,
        );

        $request = Request::create('/resource');
        $request->attributes->set('_api_operation', $operation);
        $request->attributes->set('data', $data);
        $request->attributes->set('_api_normalization_context', []);
        $response = new Response('{}');

        $listener->onPostRespond(new ResponseEvent($this->createStub(HttpKernelInterface::class), $request, HttpKernelInterface::MAIN_REQUEST, $response));

        return $response;
    }

    /**
     * @return array<string, list<string|null>>
     */
    private function headersWithoutDate(Response $response): array
    {
        $headers = $response->headers->all();
        unset($headers['date']);
        ksort($headers);

        return $headers;
    }
}
