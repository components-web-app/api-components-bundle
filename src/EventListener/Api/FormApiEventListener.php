<?php

/*
 * This file is part of the Silverback API Components Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Silverback\ApiComponentsBundle\EventListener\Api;

use ApiPlatform\Metadata\IriConverterInterface;
use Silverback\ApiComponentsBundle\Entity\Component\Form;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ResponseEvent;

/**
 * @author Daniel West <daniel@silverback.is>
 */
class FormApiEventListener
{
    public function __construct(
        private readonly IriConverterInterface $iriConverter,
    ) {
    }

    public function onPostRespond(ResponseEvent $event): void
    {
        $request = $event->getRequest();
        if (!$data = $this->getData($request)) {
            return;
        }

        $response = $event->getResponse();

        if ($formView = $data->formView) {
            $form = $formView->getForm();
            if (!$form->isValid()) {
                $response->setStatusCode(Response::HTTP_UNPROCESSABLE_ENTITY);
            }
        }

        $content = $response->getContent();
        if ($content) {
            $decoded = json_decode($content, true);
            if (isset($decoded['@id'])) {
                $canonicalIri = $this->iriConverter->getIriFromResource($data);
                if ($decoded['@id'] !== $canonicalIri) {
                    $decoded['@id'] = $canonicalIri;
                    $response->setContent(json_encode($decoded));
                }
            }
        }
    }

    public static function isSubmitRequest(Request $request): bool
    {
        $postfix = '/submit';

        return \in_array($request->getMethod(), [Request::METHOD_POST, Request::METHOD_PATCH], true)
            && 0 === substr_compare($request->getPathInfo(), $postfix, -\strlen($postfix));
    }

    private function getData(Request $request): ?Form
    {
        $data = $request->attributes->get('data');

        return $data instanceof Form && self::isSubmitRequest($request) ? $data : null;
    }
}
