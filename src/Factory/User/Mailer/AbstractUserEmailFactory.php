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

namespace Silverback\ApiComponentsBundle\Factory\User\Mailer;

use Psr\Container\ContainerInterface;
use Silverback\ApiComponentsBundle\Entity\User\AbstractUser;
use Silverback\ApiComponentsBundle\Event\UserEmailMessageEvent;
use Silverback\ApiComponentsBundle\Exception\BadMethodCallException;
use Silverback\ApiComponentsBundle\Exception\InvalidArgumentException;
use Silverback\ApiComponentsBundle\Exception\RfcComplianceException;
use Silverback\ApiComponentsBundle\Exception\UnexpectedValueException;
use Silverback\ApiComponentsBundle\Helper\RefererUrlResolver;
use Silverback\ApiComponentsBundle\Security\TokenGenerator;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Exception\RfcComplianceException as SymfonyRfcComplianceException;
use Symfony\Component\Mime\RawMessage;

/**
 * @author Daniel West <daniel@silverback.is>
 */
abstract class AbstractUserEmailFactory
{
    public const MESSAGE_ID_PREFIX = 'xx';

    protected ContainerInterface $container;
    private EventDispatcherInterface $eventDispatcher;
    protected string $subject;
    protected bool $enabled;
    protected ?string $defaultRedirectPath;
    protected ?string $redirectPathQueryKey;
    protected array $emailContext;
    protected ?RawMessage $message;
    private AbstractUser $user;

    public function __construct(ContainerInterface $container, EventDispatcherInterface $eventDispatcher, string $subject, bool $enabled = true, ?string $defaultRedirectPath = null, ?string $redirectPathQueryKey = null, array $emailContext = [])
    {
        $this->container = $container;
        $this->eventDispatcher = $eventDispatcher;
        $this->subject = $subject;
        $this->enabled = $enabled;
        $this->emailContext = $emailContext;
        $this->defaultRedirectPath = $defaultRedirectPath;
        $this->redirectPathQueryKey = $redirectPathQueryKey;
    }

    protected static function getContextKeys(): ?array
    {
        return [
            'website_name',
            'user',
        ];
    }

    protected function initUser(AbstractUser $user): void
    {
        if (!$user->getUsername()) {
            throw new InvalidArgumentException('The user must have a username set to send them any email');
        }

        if (!$user->getEmailAddress()) {
            throw new InvalidArgumentException('The user must have an email address set to send them any email');
        }

        $this->user = $user;
    }

    protected function createEmailMessage(array $context = []): ?TemplatedEmail
    {
        if (!$this->enabled) {
            return null;
        }

        if (!isset($this->user)) {
            throw new BadMethodCallException('You must call the method `initUser` before `createEmailMessage`');
        }

        try {
            // symfony/mime 5.2 deprecated fromString
            if (method_exists(Address::class, 'create')) {
                $toEmailAddress = Address::create((string) $this->user->getEmailAddress());
            } else {
                $toEmailAddress = Address::fromString((string) $this->user->getEmailAddress());
            }
        } catch (SymfonyRfcComplianceException $exception) {
            $exception = new RfcComplianceException($exception->getMessage());
            throw $exception;
        }

        $context = array_replace_recursive(
            [
                'user' => $this->user,
            ],
            $this->emailContext,
            $context
        );
        $this->validateContext($context);

        $twig = $this->container->get('twig');
        $template = $twig->createTemplate($this->subject);
        $subject = $template->render($context);

        $email = (new TemplatedEmail())
            ->to($toEmailAddress)
            ->subject($subject)
            ->htmlTemplate('@SilverbackApiComponents/emails/' . $this->getTemplate())
            ->context($context);

        $event = new UserEmailMessageEvent(static::class, $email);
        $this->eventDispatcher->dispatch($event);

        $email->getHeaders()->addTextHeader('X-Message-ID', \sprintf('%s-%s', static::MESSAGE_ID_PREFIX, TokenGenerator::generateToken()));

        return $event->getEmail();
    }

    protected function getTokenUrl(string $token, string $username, ?string $newEmail = null): string
    {
        $path = $this->populatePathVariables(
            $this->getTokenPath(),
            [
                'token' => $token,
                'username' => $username,
                'new_email' => $newEmail,
            ]
        );

        return $this->container->get(RefererUrlResolver::class)?->getAbsoluteUrl($path) ?? $path;
    }

    private function getTokenPath(): string
    {
        if (null === $this->defaultRedirectPath && null === $this->redirectPathQueryKey) {
            throw new InvalidArgumentException('The `defaultRedirectPath` or `redirectPathQueryKey` must be set');
        }

        $requestStack = $this->container->get(RequestStack::class);
        $request = $requestStack?->getMainRequest();

        $path = ($request && $this->redirectPathQueryKey) ?
            $request->query->get($this->redirectPathQueryKey, $this->defaultRedirectPath) :
            $this->defaultRedirectPath;

        if (null === $path) {
            throw new UnexpectedValueException(\sprintf('The querystring key `%s` could not be found in the request to generate a token URL', $this->redirectPathQueryKey));
        }

        return $path;
    }

    private function populatePathVariables(string $path, array $variables): string
    {
        preg_match_all('/{{[\s]*(\w+)[\s]*}}/', $path, $matches);
        foreach ($matches[0] as $matchIndex => $fullMatch) {
            if (\array_key_exists($varKey = $matches[1][$matchIndex], $variables) && null !== $variables[$varKey]) {
                $path = str_replace($fullMatch, rawurlencode($variables[$varKey]), $path);
            }
        }

        return $path;
    }

    private function validateContext(array $context): void
    {
        $contextKeys = static::getContextKeys();
        $keys = array_keys($context);
        if (\count($differences = array_diff($contextKeys, $keys))) {
            throw new InvalidArgumentException(\sprintf('You have not specified required context key(s) for the user email factory factory `%s` (expected: `%s`)', static::class, implode('`, `', $differences)));
        }
    }

    abstract public function create(AbstractUser $user, array $context = []): ?RawMessage;

    abstract protected function getTemplate(): string;
}
