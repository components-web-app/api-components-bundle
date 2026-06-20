<?php

/*
 * This file is part of the Silverback API Components Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Silverback\ApiComponentsBundle\Fixture\Placeholder;

/**
 * Generates structured placeholder HTML (or plain text) for seeding HtmlContent entities in fixtures.
 *
 * Usage:
 *   $html = (new HtmlContentPlaceholder())->generate(['paragraphs' => 3, 'includeHeadings' => true]);
 *   $htmlContent->html = $html;
 */
class HtmlContentPlaceholder
{
    public const FORMAT_HTML = 'html';
    public const FORMAT_PLAINTEXT = 'plaintext';

    public const LENGTH_SHORT = 'short';
    public const LENGTH_MEDIUM = 'medium';
    public const LENGTH_LONG = 'long';

    protected array $options = [
        'paragraphs' => 3,
        'paragraphLength' => self::LENGTH_MEDIUM,
        'includeHeadings' => false,
        'includeLists' => false,
        'includeQuotes' => false,
        'includeCode' => false,
        'includeLinks' => true,
        'format' => self::FORMAT_HTML,
    ];

    protected array $paragraphTemplates = [
        'Our Custom Web Application (CWA) empowers businesses to take control of their online presence with scalable, intuitive tools.',
        'Built with flexibility in mind, the CWA supports dynamic modules tailored to the unique workflows of growing companies.',
        'From rapid deployment to ongoing iteration, our system enables teams to manage content, customer interactions, and analytics — all from one place.',
        'Security, performance, and usability drive the foundation of the platform, ensuring peace of mind for clients and their users.',
        'The admin interface is designed to be user-friendly and accessible, so teams can get started with minimal training.',
        'We integrate seamlessly with third-party services, making it easy to connect your CRM, email platform, and more.',
        'With customizable UI components and branding options, your CWA truly reflects your company\'s identity.',
        'Performance optimization is built-in, including server-side rendering and responsive design out of the box.',
    ];

    protected array $headings = [
        'Why Choose Our CWA?',
        'Key Benefits',
        'How It Works',
        'Tailored for Growth',
        'Modular Architecture',
        'Effortless Content Management',
    ];

    protected array $listItems = [
        'Drag-and-drop page builder',
        'Role-based access control',
        'SEO-friendly routing',
        'Real-time notifications',
        'Analytics dashboard',
        'API-first design',
        'Live preview mode',
        'Multilingual support',
        'Component library with dark mode',
        'Automated deployment pipeline',
    ];

    protected array $codeSnippets = [
        "fetch('/api/v1/content', { method: 'GET' })",
        '<component is="UserCard" :user="user" />',
        'const user = await auth.login(email, password);',
        "cwa.renderComponent('Dashboard', userContext);",
    ];

    protected array $quotes = [
        'We switched to the CWA and reduced deployment time by 40%.',
        'The flexibility of the system let us scale without rewriting our stack.',
        'Our marketing team actually enjoys using the CMS now.',
        'Clients have praised the speed and responsiveness of the new site.',
        'We feel supported — not just technically, but strategically too.',
    ];

    protected array $links = [
        'here is a link' => 'https://cwa.rocks',
        'welcome to the link world' => 'https://cwa.rocks',
        'link me up Scotty' => 'https://cwa.rocks',
        'linky mc link face' => 'https://cwa.rocks',
    ];

    public function __construct(array $options = [])
    {
        $this->setOptions($options);
    }

    public function setOptions(array $options): void
    {
        $this->options = array_merge($this->options, $options);
    }

    public function generate(array $options = []): string
    {
        $options = array_merge($this->options, $options);

        $output = [];

        $totalParagraphs = $options['paragraphs'];
        for ($i = 0; $i < $totalParagraphs; ++$i) {
            $output[] = $this->renderParagraph($options);
        }

        if ($options['includeHeadings']) {
            array_splice($output, 0, 0, $this->renderHeading($options));
            $output = $this->insertNewOutput($output, $totalParagraphs - 1, fn () => $this->renderHeading($options));
        }

        $insertables = [
            'includeLists' => fn () => $this->renderList($options),
            'includeQuotes' => fn () => $this->renderQuote($options),
            'includeCode' => fn () => $this->renderCode($options),
        ];

        foreach ($insertables as $flag => $callback) {
            if ($options[$flag]) {
                $output = $this->insertNewOutput($output, $totalParagraphs, $callback);
            }
        }

        return implode("\n\n", $output);
    }

    private function insertNewOutput(array $output, int $maxInserts, callable $callback): array
    {
        if ($maxInserts < 1) {
            return $output;
        }
        $toInsert = random_int(1, $maxInserts);
        for ($i = 0; $i < $toInsert; ++$i) {
            $index = \count($output) > 0 ? random_int(0, \count($output) - 1) : 0;
            array_splice($output, $index, 0, $callback());
        }

        return $output;
    }

    private function renderHeading(array $options): string
    {
        $heading = $this->randomElement($this->headings);

        return $this->format("<h2>{$heading}</h2>", $heading, $options);
    }

    private function renderParagraph(array $options): string
    {
        $sentences = $this->randomSelection($this->paragraphTemplates, $this->getParagraphSentenceCount($options));
        $text = implode(' ', $sentences);

        if ($options['includeLinks']) {
            $text = $this->insertLinks($text, $options);
        }

        return $this->format("<p>{$text}</p>", $text, $options);
    }

    private function getParagraphSentenceCount(array $options): int
    {
        return match ($options['paragraphLength']) {
            self::LENGTH_SHORT => random_int(1, 2),
            self::LENGTH_LONG => random_int(5, 7),
            default => random_int(3, 4),
        };
    }

    private function insertLinks(string $text, array $options): string
    {
        $phrases = array_keys($this->links);
        shuffle($phrases);
        $numLinks = random_int(1, min(2, \count($phrases)));

        for ($i = 0; $i < $numLinks; ++$i) {
            $phrase = $phrases[$i];
            $url = $this->links[$phrase];

            if (!str_contains($text, $phrase)) {
                $words = explode(' ', $text);
                $insertAt = random_int(0, \count($words) - 1);
                $linkedPhrase = self::FORMAT_HTML === $options['format']
                    ? "<a href=\"{$url}\">{$phrase}</a>"
                    : "{$phrase} ({$url})";
                array_splice($words, $insertAt, 0, $linkedPhrase);
                $text = implode(' ', $words);
            }
        }

        return $text;
    }

    private function renderList(array $options): string
    {
        $items = $this->randomSelection($this->listItems, random_int(3, 6));
        $tags = ['ul', 'ol'];
        $tag = $tags[array_rand($tags)];
        $html = "<{$tag}>\n";
        foreach ($items as $item) {
            $html .= "<li>{$item}</li>\n";
        }
        $html .= "</{$tag}>";

        $plain = '- ' . implode("\n- ", $items);

        return $this->format($html, $plain, $options);
    }

    private function renderQuote(array $options): string
    {
        $quote = $this->randomElement($this->quotes);

        return $this->format("<blockquote>{$quote}</blockquote>", "\"{$quote}\"", $options);
    }

    private function renderCode(array $options): string
    {
        $code = $this->randomElement($this->codeSnippets);

        return $this->format("<pre><code>{$code}</code></pre>", $code, $options);
    }

    private function format(string $html, string $plain, array $options): string
    {
        return self::FORMAT_PLAINTEXT === $options['format'] ? $plain : $html;
    }

    private function randomElement(array $array): string
    {
        return $array[array_rand($array)];
    }

    private function randomSelection(array $array, int $count): array
    {
        shuffle($array);

        return \array_slice($array, 0, $count);
    }
}
