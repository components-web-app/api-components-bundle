<?php

/*
 * This file is part of the Silverback API Components Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Silverback\ApiComponentsBundle\Action\User;

use Silverback\ApiComponentsBundle\Exception\UnexpectedValueException;
use Silverback\ApiComponentsBundle\Helper\User\PasswordManager;
use Silverback\ApiComponentsBundle\Serializer\SerializeFormatResolver;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Serializer\Encoder\DecoderInterface;

/**
 * @author Daniel West <daniel@silverback.is>
 */
class PasswordUpdateAction
{
    private PasswordManager $passwordManager;
    private SerializeFormatResolver $serializeFormatResolver;
    private DecoderInterface $decoder;

    public function __construct(PasswordManager $passwordManager, SerializeFormatResolver $serializeFormatResolver, DecoderInterface $decoder)
    {
        $this->passwordManager = $passwordManager;
        $this->serializeFormatResolver = $serializeFormatResolver;
        $this->decoder = $decoder;
    }

    public function __invoke(Request $request): Response
    {
        $format = $this->serializeFormatResolver->getFormatFromRequest($request);
        $data = $this->decoder->decode($request->getContent(), $format);
        $requiredKeys = ['username', 'token', 'password'];
        foreach ($requiredKeys as $requiredKey) {
            if (!isset($data[$requiredKey])) {
                throw new BadRequestHttpException(sprintf('the key `%s` was not found in POST data', $requiredKey));
            }
        }
        try {
            $this->passwordManager->passwordReset($data['username'], $data['token'], $data['password']);

            return new Response(null, Response::HTTP_OK);
        } catch (UnexpectedValueException $exception) {
            return new Response(null, Response::HTTP_NOT_FOUND);
        }
    }
}
