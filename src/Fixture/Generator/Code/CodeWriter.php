<?php

/*
 * This file is part of the Silverback API Components Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Silverback\ApiComponentsBundle\Fixture\Generator\Code;

/**
 * @internal
 */
final class CodeWriter implements \Stringable
{
    public const int LINE_LENGTH = 120;

    private const string INDENT = '    ';
    private const string CHAINED = self::INDENT . '->';

    /** @var list<string> */
    private array $lines = [];

    private int $level = 0;

    public function line(string $code): self
    {
        foreach (explode("\n", $code) as $line) {
            $this->lines[] = '' === $line ? '' : str_repeat(self::INDENT, $this->level) . $line;
        }

        return $this;
    }

    public function blank(): self
    {
        if ([] !== $this->lines && '' !== end($this->lines)) {
            $this->lines[] = '';
        }

        return $this;
    }

    public function indent(): self
    {
        ++$this->level;

        return $this;
    }

    public function outdent(): self
    {
        $this->level = max(0, $this->level - 1);

        return $this;
    }

    /**
     * @param list<string> $calls
     */
    public function chain(string $head, array $calls): self
    {
        foreach ($calls as $call) {
            $head .= "\n" . self::CHAINED . self::continued($call);
        }

        return $this->line($head . ';');
    }

    /**
     * @param list<string> $arguments
     */
    public function chainedCall(string $name, array $arguments): string
    {
        return $this->call($name, $arguments, \strlen(self::CHAINED));
    }

    /**
     * @param list<string> $arguments
     */
    public function call(string $name, array $arguments, int $offset = 0): string
    {
        $call = $name . '(' . implode(', ', $arguments) . ')';
        if (!str_contains($call, "\n") && $this->level * \strlen(self::INDENT) + $offset + \strlen($call) <= self::LINE_LENGTH) {
            return $call;
        }

        $broken = $name . '(';
        foreach ($arguments as $argument) {
            $broken .= "\n" . self::INDENT . self::continued($argument) . ',';
        }

        return $broken . "\n)";
    }

    public function __toString(): string
    {
        return [] === $this->lines ? '' : implode("\n", $this->lines) . "\n";
    }

    public static function continued(string $code): string
    {
        return preg_replace('/\n(?=.)/', "\n" . self::INDENT, $code) ?? $code;
    }
}
