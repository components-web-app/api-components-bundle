<?php

namespace Silverback\ApiComponentBundle\Controller;

use BadMethodCallException;
use Doctrine\ORM\EntityManagerInterface;
use InvalidArgumentException;
use LogicException;
use Silverback\ApiComponentBundle\Dto\Form\FormView;
use Silverback\ApiComponentBundle\Entity\Component\Form\Form;
use Silverback\ApiComponentBundle\Factory\Form\FormFactory;
use Silverback\ApiComponentBundle\Form\Handler\ContextProviderInterface;
use Silverback\ApiComponentBundle\Form\Handler\FormHandlerInterface;
use Silverback\ApiComponentBundle\Validator\ClassNameValidator;
use Symfony\Component\Form\Exception\AlreadySubmittedException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Serializer\SerializerInterface;
use UnexpectedValueException;

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
     * @throws BadRequestHttpException
     * @throws AlreadySubmittedException
     * @throws UnexpectedValueException
     * @throws InvalidArgumentException
     * @throws BadMethodCallException
     * @throws LogicException
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
        $data->setForm(new FormView($form->createView(), $form));
        $context = null;
        if ($valid && $data->getSuccessHandler()) {
            foreach ($this->handlers as $handler) {
                if (ClassNameValidator::isClassSame($data->getSuccessHandler(), $handler)) {
                    $result = $handler->success($data, $form->getData(), $request);
                    if ($handler instanceof ContextProviderInterface) {
                        $context = $handler->getContext();
                    }
                    if ($result instanceof Response) {
                        return $result;
                    }
                    if ($result) {
                        $data = $result;
                    }
                    break;
                }
            }
        }
        return $this->getResponse($data, $_format, $valid, null, null, $context);
    }
}
