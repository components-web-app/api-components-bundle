<?php

/*
 * This file is part of the Silverback API Components Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Silverback\ApiComponentsBundle\Tests\DataProcessor\StateProcessor;

use ApiPlatform\Metadata\Get;
use ApiPlatform\State\ProcessorInterface;
use PHPUnit\Framework\TestCase;
use Silverback\ApiComponentsBundle\DataProcessor\StateProcessor\FormSerializeStateProcessor;
use Silverback\ApiComponentsBundle\Entity\Component\Form;
use Silverback\ApiComponentsBundle\Factory\Form\FormViewFactory;
use Silverback\ApiComponentsBundle\Helper\Form\FormSubmitHelper;
use Silverback\ApiComponentsBundle\Model\Form\FormView;
use Silverback\ApiComponentsBundle\Serializer\SerializeFormatResolverInterface;
use Silverback\ApiComponentsBundle\Tests\Functional\TestBundle\Entity\DummyComponent;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Serializer\Encoder\DecoderInterface;

class FormSerializeStateProcessorTest extends TestCase
{
    public function test_a_form_read_outside_a_submit_gets_its_view_and_is_serialized(): void
    {
        $form = new Form();
        $view = $this->view(true);
        $submitHelper = $this->createMock(FormSubmitHelper::class);
        $submitHelper->expects(self::never())->method('process');

        $result = $this->processor($this->viewFactory($form, $view), $submitHelper)->process($form, new Get(), [], ['request' => Request::create('/component/forms/1', 'GET')]);

        self::assertSame(['serialized', $form], $result);
        self::assertSame($view, $form->formView);
    }

    public function test_a_valid_post_submit_is_handled_as_a_success_and_its_result_serialized(): void
    {
        $form = new Form();
        $success = new Form();
        $request = $this->submitRequest('POST');
        $submitHelper = $this->createMock(FormSubmitHelper::class);
        $submitHelper->expects(self::once())->method('process')->with($form, ['name' => 'John'], false)->willReturnCallback(static function (Form $form): Form {
            return $form;
        });
        $submitHelper->expects(self::once())->method('handleSuccess')->with($form)->willReturn($success);

        $result = $this->processor($this->viewFactory($form, $this->view(true)), $submitHelper)->process($form, new Get(), [], ['request' => $request]);

        self::assertSame(['serialized', $success], $result);
        self::assertSame($success, $request->attributes->get('data'));
    }

    public function test_a_success_handler_with_no_result_keeps_the_submitted_form(): void
    {
        $form = new Form();
        $request = $this->submitRequest('POST');
        $submitHelper = $this->createStub(FormSubmitHelper::class);
        $submitHelper->method('process')->willReturnArgument(0);
        $submitHelper->method('handleSuccess')->willReturn(null);

        self::assertSame(['serialized', $form], $this->processor($this->viewFactory($form, $this->view(true)), $submitHelper)->process($form, new Get(), [], ['request' => $request]));
        self::assertSame($form, $request->attributes->get('data'));
    }

    public function test_a_success_response_is_passed_on_and_not_set_as_the_request_data(): void
    {
        $form = new Form();
        $response = new Response();
        $request = $this->submitRequest('POST');
        $request->attributes->set('data', $form);
        $submitHelper = $this->createStub(FormSubmitHelper::class);
        $submitHelper->method('process')->willReturnArgument(0);
        $submitHelper->method('handleSuccess')->willReturn($response);

        self::assertSame(['serialized', $response], $this->processor($this->viewFactory($form, $this->view(true)), $submitHelper)->process($form, new Get(), [], ['request' => $request]));
        self::assertSame($form, $request->attributes->get('data'));
    }

    public function test_an_invalid_post_submit_is_not_a_success(): void
    {
        $form = new Form();
        $submitHelper = $this->createMock(FormSubmitHelper::class);
        $submitHelper->method('process')->willReturnArgument(0);
        $submitHelper->expects(self::never())->method('handleSuccess');

        self::assertSame(['serialized', $form], $this->processor($this->viewFactory($form, $this->view(false)), $submitHelper)->process($form, new Get(), [], ['request' => $this->submitRequest('POST')]));
    }

    public function test_a_patch_submit_is_partial_and_never_a_success(): void
    {
        $form = new Form();
        $submitHelper = $this->createMock(FormSubmitHelper::class);
        $submitHelper->expects(self::once())->method('process')->with($form, ['name' => 'John'], true)->willReturnArgument(0);
        $submitHelper->expects(self::never())->method('handleSuccess');

        self::assertSame(['serialized', $form], $this->processor($this->viewFactory($form, $this->view(true)), $submitHelper)->process($form, new Get(), [], ['request' => $this->submitRequest('PATCH')]));
    }

    public function test_a_put_to_submit_is_not_a_submission(): void
    {
        $form = new Form();
        $submitHelper = $this->createMock(FormSubmitHelper::class);
        $submitHelper->expects(self::never())->method('process');

        self::assertSame(['serialized', $form], $this->processor($this->viewFactory($form, $this->view(true)), $submitHelper)->process($form, new Get(), [], ['request' => $this->submitRequest('PUT')]));
    }

    public function test_a_form_without_a_request_only_gets_its_view(): void
    {
        $form = new Form();
        $submitHelper = $this->createMock(FormSubmitHelper::class);
        $submitHelper->expects(self::never())->method('process');

        self::assertSame(['serialized', $form], $this->processor($this->viewFactory($form, $this->view(true)), $submitHelper)->process($form, new Get()));
    }

    public function test_anything_else_is_serialized_unchanged(): void
    {
        $component = new DummyComponent();
        $viewFactory = $this->createMock(FormViewFactory::class);
        $viewFactory->expects(self::never())->method('create');

        self::assertSame(['serialized', $component], $this->processor($viewFactory, $this->createStub(FormSubmitHelper::class))->process($component, new Get(), [], ['request' => $this->submitRequest('POST')]));
    }

    private function submitRequest(string $method): Request
    {
        return Request::create('/component/forms/1/submit', $method, [], [], [], [], '{"name":"John"}');
    }

    private function view(bool $valid): FormView
    {
        $symfonyForm = $this->createStub(FormInterface::class);
        $symfonyForm->method('isValid')->willReturn($valid);
        $view = $this->createStub(FormView::class);
        $view->method('getForm')->willReturn($symfonyForm);

        return $view;
    }

    private function viewFactory(Form $form, FormView $view): FormViewFactory
    {
        $viewFactory = $this->createMock(FormViewFactory::class);
        $viewFactory->expects(self::once())->method('create')->with($form)->willReturn($view);

        return $viewFactory;
    }

    private function processor(FormViewFactory $viewFactory, FormSubmitHelper $submitHelper): FormSerializeStateProcessor
    {
        $inner = $this->createStub(ProcessorInterface::class);
        $inner->method('process')->willReturnCallback(static fn (mixed $data): array => ['serialized', $data]);
        $formatResolver = $this->createStub(SerializeFormatResolverInterface::class);
        $formatResolver->method('getFormatFromRequest')->willReturn('json');
        $decoder = $this->createStub(DecoderInterface::class);
        $decoder->method('decode')->willReturnCallback(static fn (string $content): array => json_decode($content, true));

        return new FormSerializeStateProcessor($inner, $viewFactory, $submitHelper, $formatResolver, $decoder);
    }
}
