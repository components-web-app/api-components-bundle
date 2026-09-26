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

use ApiPlatform\Metadata\IriConverterInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Silverback\ApiComponentsBundle\Entity\Component\Form;
use Silverback\ApiComponentsBundle\EventListener\Api\FormApiEventListener;
use Silverback\ApiComponentsBundle\Model\Form\FormView;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;

class FormApiEventListenerTest extends TestCase
{
    /**
     * @return iterable<string, array{string, string, bool}>
     */
    public static function submitRequests(): iterable
    {
        yield 'POST to /submit' => [Request::METHOD_POST, '/component/forms/abc/submit', true];
        yield 'PATCH to /submit' => [Request::METHOD_PATCH, '/component/forms/abc/submit', true];
        yield 'PUT to /submit' => [Request::METHOD_PUT, '/component/forms/abc/submit', false];
        yield 'GET to /submit' => [Request::METHOD_GET, '/component/forms/abc/submit', false];
        yield 'POST elsewhere' => [Request::METHOD_POST, '/component/forms/abc', false];
        yield 'POST to a path containing submit' => [Request::METHOD_POST, '/component/forms/submit/abc', false];
    }

    #[DataProvider('submitRequests')]
    public function test_a_submit_is_a_post_or_patch_to_a_path_ending_in_submit(string $method, string $path, bool $expected): void
    {
        self::assertSame($expected, FormApiEventListener::isSubmitRequest(Request::create($path, $method)));
    }

    public function test_an_invalid_submit_response_is_unprocessable_and_carries_the_canonical_iri(): void
    {
        $form = new Form();
        $form->formView = $this->formView(false);
        $iriConverter = $this->createStub(IriConverterInterface::class);
        $iriConverter->method('getIriFromResource')->willReturn('/component/forms/abc');
        $response = new Response('{"@id":"/component/forms/abc/submit"}');

        (new FormApiEventListener($iriConverter))->onPostRespond($this->event(Request::METHOD_POST, $form, $response));

        self::assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $response->getStatusCode());
        self::assertSame('{"@id":"\/component\/forms\/abc"}', $response->getContent());
    }

    public function test_a_valid_submit_response_keeps_its_status(): void
    {
        $form = new Form();
        $form->formView = $this->formView(true);
        $iriConverter = $this->createStub(IriConverterInterface::class);
        $iriConverter->method('getIriFromResource')->willReturn('/component/forms/abc');
        $response = new Response('{"@id":"/component/forms/abc"}');

        (new FormApiEventListener($iriConverter))->onPostRespond($this->event(Request::METHOD_POST, $form, $response));

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());
        self::assertSame('{"@id":"/component/forms/abc"}', $response->getContent());
    }

    public function test_a_response_that_is_not_a_submit_is_left_alone(): void
    {
        $form = new Form();
        $form->formView = $this->formView(false);
        $iriConverter = $this->createMock(IriConverterInterface::class);
        $iriConverter->expects(self::never())->method('getIriFromResource');
        $response = new Response('{"@id":"/component/forms/abc/submit"}');

        (new FormApiEventListener($iriConverter))->onPostRespond($this->event(Request::METHOD_PUT, $form, $response));

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());
    }

    private function formView(bool $valid): FormView
    {
        $symfonyForm = $this->createStub(FormInterface::class);
        $symfonyForm->method('isValid')->willReturn($valid);
        $formView = $this->createStub(FormView::class);
        $formView->method('getForm')->willReturn($symfonyForm);

        return $formView;
    }

    private function event(string $method, Form $form, Response $response): ResponseEvent
    {
        $request = Request::create('/component/forms/abc/submit', $method);
        $request->attributes->set('data', $form);

        return new ResponseEvent($this->createStub(HttpKernelInterface::class), $request, HttpKernelInterface::MAIN_REQUEST, $response);
    }
}
