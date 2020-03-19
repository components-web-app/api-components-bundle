<?php

namespace Silverback\ApiComponentBundle\Controller;

use Silverback\ApiComponentBundle\Dto\Form\FormView;
use Silverback\ApiComponentBundle\Entity\Component\Form\Form;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class FormPatchAction extends AbstractFormAction
{
    private function isAssocArray(array $arr): bool
    {
        if (array() === $arr) {
            return false;
        }
        return array_keys($arr) !== range(0, count($arr) - 1);
    }

    private function arrayIsStrings(array $arr): bool
    {
        foreach ($arr as $item) {
            if (!is_string($item)) {
                return false;
            }
        }
        return true;
    }

    private function getNestedKey(FormInterface $form, array $formData): FormInterface
    {
        $child = $form->get($key = key($formData));
        while(is_array($formData = $formData[$key]) && $count = \count($formData)) {
            if (!$this->isAssocArray($formData) && $this->arrayIsStrings($formData)) {
                break;
            }

            if ($count === 1) {
                $child = $child->get($key = key($formData));
                continue;
            }
            // front-end should submit empty objects for each item in a collection up to the one we are trying to validate
            // so let us just get the last item to validate
            // key should be numeric, if not it is probably first and second for repeated field. These should both be checked...
            $key = ($count - 1);
            if (!$child->has($key)) {
                break;
            }
            $child = $child->get($key);
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
            $data->setForm($formView = new FormView($formItem->createView(), $formItem));
            return $this->getResponse($data, $_format, $this->getFormValid($formView));
        }

        $datum = [];
        $valid = true;
        foreach ($formData as $key => $value) {
            $dataItem = clone $data;
            $formItem = $this->getNestedKey($form, $formData[$key]);
            $dataItem->setForm($formView = new FormView($formItem->createView(), $formItem));
            $datum[] = $dataItem;
            if ($valid && !$this->getFormValid($formView)) {
                $valid = false;
            }
        }

        return $this->getResponse($datum, $_format, $valid);
    }
}
