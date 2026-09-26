<?php

use League\CommonMark\MarkdownConverter;
use League\CommonMark\Environment\Environment;
use League\CommonMark\Extension\CommonMark\CommonMarkCoreExtension;
use League\CommonMark\Extension\Table\TableExtension;
use League\CommonMark\Extension\Autolink\AutolinkExtension;
use League\CommonMark\Extension\TaskList\TaskListExtension;
use League\CommonMark\Extension\Attributes\AttributesExtension;
use League\CommonMark\Extension\HeadingPermalink\HeadingPermalinkExtension;
use League\CommonMark\Extension\HeadingPermalink\HeadingPermalinkProcessor;

final class Markdown
{
    private static ?MarkdownConverter $converter = null;

    private static function converter(): MarkdownConverter
    {
        if (self::$converter === null) {
            $environment = new Environment([
                'html_input' => 'strip',
                'allow_unsafe_links' => false,
                'heading_permalink' => [
                    // Headings just need a stable id for the table of contents
                    // to link to - no visible permalink anchor is rendered.
                    'insert' => HeadingPermalinkProcessor::INSERT_NONE,
                    'apply_id_to_heading' => true,
                ],
            ]);
            $environment->addExtension(new CommonMarkCoreExtension());
            $environment->addExtension(new TableExtension());
            $environment->addExtension(new AutolinkExtension());
            $environment->addExtension(new TaskListExtension());
            $environment->addExtension(new AttributesExtension());
            $environment->addExtension(new HeadingPermalinkExtension());
            self::$converter = new MarkdownConverter($environment);
        }
        return self::$converter;
    }

    public static function toHtml(string $markdown): string
    {
        return (string)self::converter()->convert($markdown);
    }

    /**
     * Pulls the H1/H2 headings (in document order, with the ids toHtml()
     * already assigned them) out of rendered material HTML, for a table of
     * contents.
     *
     * @return list<array{level: int, id: string, text: string}>
     */
    public static function extractHeadings(string $html): array
    {
        if (trim($html) === '') {
            return [];
        }

        $dom = new DOMDocument();
        libxml_use_internal_errors(true);
        $dom->loadHTML('<?xml encoding="utf-8"?><div>' . $html . '</div>');
        libxml_clear_errors();

        $headings = [];
        $xpath = new DOMXPath($dom);
        foreach ($xpath->query('//h1 | //h2') as $node) {
            $id = $node->getAttribute('id');
            $text = trim($node->textContent);
            if ($id === '' || $text === '') {
                continue;
            }
            $headings[] = [
                'level' => (int)substr($node->nodeName, 1),
                'id' => $id,
                'text' => $text,
            ];
        }
        return $headings;
    }
}
