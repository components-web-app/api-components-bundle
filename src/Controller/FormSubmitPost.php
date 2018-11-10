<?php

namespace Silverback\ApiComponentBundle\Controller;

use Doctrine\ORM\EntityManagerInterface;
use Silverback\ApiComponentBundle\Entity\Component\Form\Form;
use Silverback\ApiComponentBundle\Entity\Component\Form\FormView;
use Silverback\ApiComponentBundle\Factory\Form\FormFactory;
use Silverback\ApiComponentBundle\Form\Handler\FormHandlerInterface;
use Silverback\ApiComponentBundle\Validator\ClassNameValidator;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Serializer\SerializerInterface;

class FormSubmitPost extends AbstractForm
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
     * @throws \ReflectionException
     * @throws \LogicException
     */
    public function __invoke(Request $request, Form $data)
    {
        $contentType = $request->headers->get('CONTENT_TYPE');
        $_format = $request->attributes->get('_format') ?: $request->getFormat($contentType);

        $form = $this->formFactory->create($data);
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
                    $handler->success($data);
                    break;
                }
            }
        }
        return $this->getResponse($data, $_format, $valid);
    }
}
