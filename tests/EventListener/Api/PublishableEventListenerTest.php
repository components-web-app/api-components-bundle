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
use ApiPlatform\Validator\ValidatorInterface;
use Doctrine\Persistence\ManagerRegistry;
use PHPUnit\Framework\TestCase;
use Silverback\ApiComponentsBundle\AttributeReader\PublishableAttributeReader;
use Silverback\ApiComponentsBundle\EventListener\Api\PublishableEventListener;
use Silverback\ApiComponentsBundle\Helper\Publishable\PublishableStatusChecker;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

final class PublishableEventListenerTest extends TestCase
{
    public function test_a_response_whose_data_is_not_an_object_and_has_no_resource_class_is_left_unchanged(): void
    {
        $registry = $this->createStub(ManagerRegistry::class);
        $authorizationChecker = $this->createMock(AuthorizationCheckerInterface::class);
        $authorizationChecker->expects(self::never())->method('isGranted');
        $validator = $this->createMock(ValidatorInterface::class);
        $validator->expects(self::never())->method('validate');
        $listener = new PublishableEventListener(
            new PublishableStatusChecker($registry, new PublishableAttributeReader($registry), $authorizationChecker, "is_granted('ROLE_ADMIN')"),
            $registry,
            $validator,
        );

        $request = Request::create('/contexts/Page');
        $request->attributes->set('_api_operation', new Get());
        $request->attributes->set('data', ['@context' => ['@vocab' => 'http://example.com/docs.jsonld#']]);
        $request->attributes->set('_api_normalization_context', []);
        $response = new Response('{}');
        $headersBefore = $response->headers->all();

        $listener->onPostRespond(new ResponseEvent($this->createStub(HttpKernelInterface::class), $request, HttpKernelInterface::MAIN_REQUEST, $response));

        self::assertSame($headersBefore, $response->headers->all());
    }
}
