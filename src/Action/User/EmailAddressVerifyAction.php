<?php

/*
 * This file is part of the Silverback API Component Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Silverback\ApiComponentBundle\Action\User;

use Silverback\ApiComponentBundle\Action\AbstractAction;
use Silverback\ApiComponentBundle\Factory\Response\ResponseFactory;
use Silverback\ApiComponentBundle\Manager\User\EmailAddressManager;
use Silverback\ApiComponentBundle\Serializer\SerializeFormatResolver;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Serializer\SerializerInterface;

/**
 * @author Daniel West <daniel@silverback.is>
 */
class EmailAddressVerifyAction extends AbstractAction
{
    private EmailAddressManager $emailAddressManager;

    public function __construct(SerializerInterface $serializer, SerializeFormatResolver $requestFormatResolver, ResponseFactory $responseFactory, EmailAddressManager $emailAddressManager)
    {
        parent::__construct($serializer, $requestFormatResolver, $responseFactory);
        $this->emailAddressManager = $emailAddressManager;
    }

    public function __invoke(Request $request)
    {
        $data = $this->serializer->decode($request->getContent(), $this->requestFormatResolver->getFormatFromRequest($request), []);
        $requiredKeys = ['username', 'email', 'token'];
        foreach ($requiredKeys as $requiredKey) {
            if (!isset($data[$requiredKey])) {
                throw new BadRequestHttpException(sprintf('the key `%s` was not found in POST data', $requiredKey));
            }
        }

        try {
            $this->emailAddressManager->verifyNewEmailAddress($data['username'], $data['email'], $data['token']);

            return $this->responseFactory->create($request);
        } catch (NotFoundHttpException $exception) {
            return $this->responseFactory->create($request, $exception->getMessage(), Response::HTTP_NOT_FOUND);
        }
    }
}
