<?php

namespace Silverback\ApiComponentBundle\Controller;

use Silverback\ApiComponentBundle\Exception\InvalidEntityException;
use Silverback\ApiComponentBundle\Repository\User\UserRepository;
use Silverback\ApiComponentBundle\Security\PasswordManager;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Validator\ConstraintViolationInterface;

class PasswordResetController
{
    private $userRepository;
    private $passwordManager;

    public function __construct(
        UserRepository $userRepository,
        PasswordManager $passwordManager
    ) {
        $this->userRepository = $userRepository;
        $this->passwordManager = $passwordManager;
    }

    /**
     * @Route("/password/reset/request/{username}", name="password_reset_request", methods={"get"})
     * @param Request $request
     * @param string $username
     * @return JsonResponse
     */
    public function requestAction(Request $request, string $username): JsonResponse
    {
        $user = $this->userRepository->findOneBy(['username' => $username]);
        if (!$user) {
            return new JsonResponse([], Response::HTTP_NOT_FOUND);
        }
        $this->passwordManager->requestResetEmail($user, $request->query->get('resetPath', ''));
        return new JsonResponse([], Response::HTTP_OK);
    }

    /**
     * @Route("/password/reset", name="password_reset", methods={"post"})
     * @param Request $request
     * @return JsonResponse
     */
    public function resetAction(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        $username = $data['username'];
        $token = $data['token'];
        $user = $this->userRepository->findOneByPasswordResetToken(
            $username,
            $token
        );
        if (!$user) {
            return new JsonResponse([], Response::HTTP_NOT_FOUND);
        }

        try {
            $this->passwordManager->passwordReset($user, $data['password']);
            return new JsonResponse([], Response::HTTP_OK);
        } catch (InvalidEntityException $exception) {
            $errors = [];
            /** @var ConstraintViolationInterface $error */
            foreach ($exception->getErrors() as $error) {
                $errors[] = $error->getMessage();
            }
            return new JsonResponse($errors, Response::HTTP_BAD_REQUEST);
        }
    }
}
