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
        yield 'valid POST is a full submit and succeeds' => [Request::METHOD_POST, true, false, 1];
        yield 'invalid POST is a full submit and does not succeed' => [Request::METHOD_POST, false, false, 0];
        yield 'valid PATCH is a partial submit and only validates' => [Request::METHOD_PATCH, true, true, 0];
        yield 'invalid PATCH is a partial submit and does not succeed' => [Request::METHOD_PATCH, false, true, 0];
    }

    #[DataProvider('methodProvider')]
    public function test_post_is_a_full_submit_and_patch_is_a_partial_validate_only_submit(string $method, bool $valid, bool $expectedPartialSubmit, int $expectedSuccessCalls): void
    {
        $formView = $this->createFormView($valid);

        $formSubmitHelper = $this->createMock(FormSubmitHelper::class);
        $formSubmitHelper->expects(self::once())
            ->method('process')
            ->with(self::isInstanceOf(Form::class), ['test' => ['name' => 'John']], $expectedPartialSubmit)
            ->willReturnCallback(static function (Form $form) use ($formView) {
                $form->formView = $formView;

                return $form;
            });
        $formSubmitHelper->expects(self::exactly($expectedSuccessCalls))->method('handleSuccess')->willReturn(null);

        $form = new Form();
        $event = $this->createViewEvent($method, $form);
        $this->createListener($formSubmitHelper, $formView)->onPreSerialize($event);

        self::assertSame($form, $event->getControllerResult());
    }

    public function test_put_is_not_handled_as_a_submission(): void
    {
        $formView = $this->createFormView(true);

        $formSubmitHelper = $this->createMock(FormSubmitHelper::class);
        $formSubmitHelper->expects(self::never())->method('process');
        $formSubmitHelper->expects(self::never())->method('handleSuccess');

        $form = new Form();
        $event = $this->createViewEvent(Request::METHOD_PUT, $form);
        $this->createListener($formSubmitHelper, $formView)->onPreSerialize($event);

        self::assertSame($form, $event->getControllerResult());
    }

    private function createFormView(bool $valid): FormView
    {
        $symfonyForm = $this->createStub(FormInterface::class);
        $symfonyForm->method('isValid')->willReturn($valid);
        $formView = $this->createStub(FormView::class);
        $formView->method('getForm')->willReturn($symfonyForm);

        return $formView;
    }

    private function createListener(FormSubmitHelper $formSubmitHelper, FormView $formView): FormApiEventListener
    {
        $formViewFactory = $this->createStub(FormViewFactory::class);
        $formViewFactory->method('create')->willReturn($formView);

        return new FormApiEventListener(
            $formSubmitHelper,
            new SerializeFormatResolver(new RequestStack(), 'json'),
            new Serializer([], [new JsonEncoder()]),
            $formViewFactory,
            $this->createStub(IriConverterInterface::class),
        );
    }

    private function createViewEvent(string $method, Form $form): ViewEvent
    {
        $request = Request::create('/component/forms/abc/submit', $method, content: '{"test":{"name":"John"}}');
        $request->setRequestFormat('json');
        $request->attributes->set('data', $form);

        return new ViewEvent($this->createStub(HttpKernelInterface::class), $request, HttpKernelInterface::MAIN_REQUEST, $form);
    }
}
