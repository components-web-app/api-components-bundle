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

namespace Silverback\ApiComponentBundle\Factory\Mailer\User;

use Psr\Container\ContainerInterface;
use Silverback\ApiComponentBundle\Entity\User\AbstractUser;
use Silverback\ApiComponentBundle\Exception\BadMethodCallException;
use Silverback\ApiComponentBundle\Exception\InvalidArgumentException;
use Silverback\ApiComponentBundle\Exception\RfcComplianceException;
use Silverback\ApiComponentBundle\Exception\UnexpectedValueException;
use Silverback\ApiComponentBundle\Url\RefererUrlHelper;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Exception\RfcComplianceException as SymfonyRfcComplianceException;
use Symfony\Component\Mime\RawMessage;
use Symfony\Contracts\Service\ServiceSubscriberInterface;

/**
 * @author Daniel West <daniel@silverback.is>
 */
abstract class AbstractUserEmailFactory implements ServiceSubscriberInterface
{
    protected ContainerInterface $container;
    protected bool $enabled;
    protected string $template;
    protected string $subject;
    protected array $emailContext;
    protected ?string $defaultRedirectPath;
    protected ?string $redirectPathQueryKey;
    protected ?RawMessage $message;
    private ?AbstractUser $user;

    public function __construct(
        ContainerInterface $container,
        string $subject,
        bool $enabled = true,
        array $emailContext = [],
        ?string $defaultRedirectPath = null,
        ?string $redirectPathQueryKey = null
    ) {
        $this->container = $container;
        $this->enabled = $enabled;
        $this->subject = $subject;
        $this->emailContext = $emailContext;
        $this->defaultRedirectPath = $defaultRedirectPath;
        $this->redirectPathQueryKey = $redirectPathQueryKey;
    }

    public static function getSubscribedServices(): array
    {
        return [
            RequestStack::class,
            RefererUrlHelper::class,
        ];
    }

    abstract public function create(AbstractUser $user, array $context = []): ?RawMessage;

    abstract protected function getTemplate(): string;

    protected static function getRequiredContextKeys(): ?array
    {
        return [
            'website_name',
            'user',
        ];
    }

    protected function createEmailMessage(array $context = []): TemplatedEmail
    {
        if (!$this->user) {
            throw new BadMethodCallException('You must call the method `validateUser` before `createEmailMessage`');
        }

        try {
            $toEmailAddress = Address::fromString($this->user->getEmailAddress());
        } catch (SymfonyRfcComplianceException $exception) {
            $exception = new RfcComplianceException($exception->getMessage());
            throw $exception;
        }

        $context = array_replace_recursive([
            'user' => $this->user,
        ], $this->emailContext, $context);
        $this->validateContext($context);

        return (new TemplatedEmail())
            ->to($toEmailAddress)
            ->subject($this->subject)
            ->htmlTemplate('@SilverbackApiComponent/emails/' . $this->getTemplate())
            ->context($context);
    }

    protected function initUser(AbstractUser $user): void
    {
        if (!$user->getUsername()) {
            throw new InvalidArgumentException('The user must have a username set to send them any email');
        }

        if (!$userEmailAddress = $user->getEmailAddress()) {
            throw new InvalidArgumentException('The user must have a username set to send them any email');
        }

        $this->user = $user;
    }

    protected function getTokenUrl(string $token, string $username): string
    {
        $path = $this->populatePathVariables($this->getTokenPath(), [
            'token' => $token,
            'username' => $username,
        ]);

        $refererUrlHelper = $this->container->get(RefererUrlHelper::class);

        return $refererUrlHelper->getAbsoluteUrl($path);
    }

    private function getTokenPath(): string
    {
        if (null === $this->defaultRedirectPath && null === $this->redirectPathQueryKey) {
            throw new InvalidArgumentException('The `defaultRedirectPath` or `redirectPathQueryKey` must be set');
        }

        $requestStack = $this->container->get(RequestStack::class);
        $request = $requestStack->getMasterRequest();

        $path = ($request && $this->redirectPathQueryKey) ?
            $request->query->get($this->redirectPathQueryKey, $this->defaultRedirectPath) :
            $this->defaultRedirectPath;

        if (null === $path) {
            throw new UnexpectedValueException(sprintf('The querystring key `%s` could not be found in the request to generate a token URL', $this->redirectPathQueryKey));
        }

        return $path;
    }

    private function populatePathVariables(string $path, array $variables): string
    {
        preg_match_all('/{{[\s]*(\w+)[\s]*}}/', $path, $matches);
        foreach ($matches[0] as $matchIndex => $fullMatch) {
            if (isset($variables[${$matches[1][$matchIndex]}])) {
                $path = str_replace($fullMatch, $variables[${$matches[1][$matchIndex]}], $path);
            }
        }

        return $path;
    }

    private function validateContext(array $context): void
    {
        $requiredKeys = self::getRequiredContextKeys();
        foreach ($requiredKeys as $requiredKey) {
            if (!\array_key_exists($requiredKey, $context)) {
                throw new InvalidArgumentException(sprintf('The context key `%s` is required to create the email message', $requiredKey));
            }
        }
    }
}
