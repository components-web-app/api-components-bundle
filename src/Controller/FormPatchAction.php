<?php

declare(strict_types=1);

namespace Silverback\ApiComponentBundle\Controller;

use Silverback\ApiComponentBundle\Dto\Form\FormView;
use Silverback\ApiComponentBundle\Entity\Component\Form\Form;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class FormPatchAction extends AbstractFormAction
{
    /**
     * @param Request $request
     * @param Form $data
     * @return Response
     */
    public function __invoke(Request $request, Form $data)
    {
        $contentType = $request->headers->get('CONTENT_TYPE');
        $_format = $request->attributes->get('_format') ?: $request->getFormat($contentType);

        $form = $this->formFactory->create($data);
        $formData = $this->deserializeFormData($form, $request->getContent());
        $form->submit($formData, false);

        $dataCount = \count($formData);
        if ($dataCount === 1) {
            $data->setForm(new FormView($form->get(key($formData))->createView()));
            return $this->getResponse($data, $_format, $this->getFormValid($data->getForm()));
        }

        $datum = [];
        $valid = true;
        foreach ($formData as $key => $value) {
            $dataItem = clone $data;
            $dataItem->setForm(new FormView($form->get($key)->createView()));
            $datum[] = $dataItem;
            if ($valid && !$this->getFormValid($dataItem->getForm())) {
                $valid = false;
            }
        }

        return $this->getResponse($datum, $_format, $valid);
    }
}
