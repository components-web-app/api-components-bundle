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

use Silverback\ApiComponentsBundle\Exception\InvalidArgumentException;
use Silverback\ApiComponentsBundle\Helper\User\EmailAddressManager;
use Symfony\Component\HttpFoundation\Response;

/**
 * @author Daniel West <daniel@silverback.is>
 */
class VerifyEmailAddressAction
{
    private EmailAddressManager $emailAddressManager;

    public function __construct(EmailAddressManager $emailAddressManager)
    {
        $this->emailAddressManager = $emailAddressManager;
    }

    public function __invoke(string $username, string $token): Response
    {
        try {
            $this->emailAddressManager->verifyEmailAddress($username, $token);
        } catch (InvalidArgumentException $exception) {
            return new Response(null, Response::HTTP_NOT_FOUND);
        }

        $response = new Response(null, Response::HTTP_OK);
        $response->setCache([
            'private' => true,
            's_maxage' => 0,
            'max_age' => 0,
        ]);

        return $response;
    }
}
