<?php

namespace Silverback\ApiComponentBundle\Controller;

use Silverback\ApiComponentBundle\Dto\Form\FormView;
use Silverback\ApiComponentBundle\Entity\Component\Form\Form;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class FormPatchAction extends AbstractFormAction
{
    private function getNestedKey(FormInterface $form, array $formData): FormInterface
    {
        $child = $form->get($key = key($formData));
        while(is_array($formData = $formData[$key]) && \count($formData) === 1) {
            $child = $child->get($key = key($formData));
        }
        return $child;
    }

    /**
     * @param Request $request
     * @param Form $data
     * @return Response
     */
    public function __invoke(Request $request, Form $data)
    {
        $contentType = $request->headers->get('CONTENT_TYPE');
        $_format = $request->attributes->get('_format') ?: $request->getFormat($contentType);

        $builder = $this->formFactory->create($data);
        $form = $builder->getForm();
        $formData = $this->deserializeFormData($form, $request->getContent());
        $form->submit($formData, false);

        $dataCount = \count($formData);
        if ($dataCount === 1) {
            $formItem = $this->getNestedKey($form, $formData);
            $data->setForm(new FormView($formItem->createView()));
            return $this->getResponse($data, $_format, $this->getFormValid($data->getForm()));
        }

        $datum = [];
        $valid = true;
        foreach ($formData as $key => $value) {
            $dataItem = clone $data;
            $formItem = $this->getNestedKey($form, $formData[$key]);
            $dataItem->setForm(new FormView($formItem->createView()));
            $datum[] = $dataItem;
            if ($valid && !$this->getFormValid($dataItem->getForm())) {
                $valid = false;
            }
        }

        return $this->getResponse($datum, $_format, $valid);
    }
}
