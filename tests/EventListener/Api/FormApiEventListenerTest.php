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
use Silverback\ApiComponentsBundle\Factory\Form\FormViewFactory;
use Silverback\ApiComponentsBundle\Helper\Form\FormSubmitHelper;
use Silverback\ApiComponentsBundle\Model\Form\FormView;
use Silverback\ApiComponentsBundle\Serializer\SerializeFormatResolver;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Event\ViewEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Serializer;

class FormApiEventListenerTest extends TestCase
{
    public static function methodProvider(): iterable
    {
        yield 'POST submits' => [Request::METHOD_POST, true, 1];
        yield 'PUT submits' => [Request::METHOD_PUT, true, 1];
        yield 'PATCH only validates' => [Request::METHOD_PATCH, true, 0];
        yield 'invalid POST does not submit' => [Request::METHOD_POST, false, 0];
        yield 'invalid PUT does not submit' => [Request::METHOD_PUT, false, 0];
        yield 'invalid PATCH does not submit' => [Request::METHOD_PATCH, false, 0];
    }

    #[DataProvider('methodProvider')]
    public function test_form_success_is_handled_only_for_valid_post_and_put(string $method, bool $valid, int $expectedSuccessCalls): void
    {
        $symfonyForm = $this->createStub(FormInterface::class);
        $symfonyForm->method('isValid')->willReturn($valid);
        $formView = $this->createStub(FormView::class);
        $formView->method('getForm')->willReturn($symfonyForm);

        $formSubmitHelper = $this->createMock(FormSubmitHelper::class);
        $formSubmitHelper->method('process')->willReturnCallback(static function (Form $form) use ($formView) {
            $form->formView = $formView;

            return $form;
        });
        $formSubmitHelper->expects(self::exactly($expectedSuccessCalls))->method('handleSuccess')->willReturn(null);

        $formViewFactory = $this->createStub(FormViewFactory::class);
        $formViewFactory->method('create')->willReturn($formView);

        $listener = new FormApiEventListener(
            $formSubmitHelper,
            new SerializeFormatResolver(new RequestStack(), 'json'),
            new Serializer([], [new JsonEncoder()]),
            $formViewFactory,
            $this->createStub(IriConverterInterface::class),
        );

        $form = new Form();
        $request = Request::create('/component/forms/abc/submit', $method, content: '{"test":{"name":"John"}}');
        $request->setRequestFormat('json');
        $request->attributes->set('data', $form);

        $event = new ViewEvent($this->createStub(HttpKernelInterface::class), $request, HttpKernelInterface::MAIN_REQUEST, $form);
        $listener->onPreSerialize($event);

        self::assertSame($form, $event->getControllerResult());
    }
}
