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

use Doctrine\ORM\EntityManagerInterface;
use Silverback\ApiComponentsBundle\Exception\InvalidArgumentException;
use Silverback\ApiComponentsBundle\Exception\UnexpectedValueException;
use Silverback\ApiComponentsBundle\Helper\User\UserDataProcessor;
use Silverback\ApiComponentsBundle\Helper\User\UserMailer;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * @author Daniel West <daniel@silverback.is>
 */
class PasswordRequestAction
{
    private UserDataProcessor $userDataProcessor;
    private EntityManagerInterface $entityManager;
    private UserMailer $mailer;

    public function __construct(UserDataProcessor $userDataProcessor, EntityManagerInterface $entityManager, UserMailer $mailer)
    {
        $this->userDataProcessor = $userDataProcessor;
        $this->entityManager = $entityManager;
        $this->mailer = $mailer;
    }

    public function __invoke(Request $request, string $username)
    {
        try {
            $user = $this->userDataProcessor->updatePasswordConfirmationToken($username);
        } catch (InvalidArgumentException $e) {
            return new Response(null, Response::HTTP_NOT_FOUND);
        } catch (UnexpectedValueException $e) {
            return new Response(null, Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        if ($user) {
            $this->entityManager->flush();
            $this->mailer->sendPasswordResetEmail($user);
            $user->setPasswordRequestedAt(new \DateTime());
            $this->entityManager->flush();
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
