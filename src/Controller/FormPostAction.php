<?php

namespace Silverback\ApiComponentBundle\Controller;

use Doctrine\ORM\EntityManagerInterface;
use Silverback\ApiComponentBundle\Dto\Form\FormView;
use Silverback\ApiComponentBundle\Entity\Component\Form\Form;
use Silverback\ApiComponentBundle\Factory\Form\FormFactory;
use Silverback\ApiComponentBundle\Form\Handler\FormHandlerInterface;
use Silverback\ApiComponentBundle\Validator\ClassNameValidator;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Serializer\SerializerInterface;

class FormPostAction extends AbstractFormAction
{
    /**
     * @var iterable|FormHandlerInterface[]
     */
    private $handlers;

    public function __construct(
        EntityManagerInterface $entityManager,
        SerializerInterface $serializer,
        FormFactory $formFactory,
        iterable $formHandlers
    ) {
        parent::__construct($entityManager, $serializer, $formFactory);
        $this->handlers = $formHandlers;
    }

    /**
     * @param Request $request
     * @param Form $data
     * @return Response
     * @throws \Symfony\Component\HttpKernel\Exception\BadRequestHttpException
     * @throws \Symfony\Component\Form\Exception\AlreadySubmittedException
     * @throws \UnexpectedValueException
     * @throws \InvalidArgumentException
     * @throws \Symfony\Component\Form\Exception\LogicException
     * @throws \BadMethodCallException
     * @throws \LogicException
     */
    public function __invoke(Request $request, Form $data)
    {
        $contentType = $request->headers->get('CONTENT_TYPE');
        $_format = $request->attributes->get('_format') ?: $request->getFormat($contentType);

        $builder = $this->formFactory->create($data);
        $form = $builder->getForm();
        $formData = $this->deserializeFormData($form, $request->getContent());
        $form->submit($formData);
        if (!$form->isSubmitted()) {
            return $this->getResponse($data, $_format, false);
        }
        $valid = $form->isValid();
        $data->setForm(new FormView($form->createView()));
        if ($valid && $data->getSuccessHandler()) {
            foreach ($this->handlers as $handler) {
                if (ClassNameValidator::isClassSame($data->getSuccessHandler(), $handler)) {
                    if ($response = $handler->success($data, $form->getData(), $request)) {
                        return $response;
                    }
                    break;
                }
            }
        }
        return $this->getResponse($data, $_format, $valid);
    }
}
