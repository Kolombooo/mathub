<?php

use League\CommonMark\MarkdownConverter;
use League\CommonMark\Environment\Environment;
use League\CommonMark\Extension\CommonMark\CommonMarkCoreExtension;
use League\CommonMark\Extension\Table\TableExtension;
use League\CommonMark\Extension\Autolink\AutolinkExtension;
use League\CommonMark\Extension\TaskList\TaskListExtension;
use League\CommonMark\Extension\Attributes\AttributesExtension;

final class Markdown
{
    private static ?MarkdownConverter $converter = null;

    private static function converter(): MarkdownConverter
    {
        if (self::$converter === null) {
            $environment = new Environment([
                'html_input' => 'strip',
                'allow_unsafe_links' => false,
            ]);
            $environment->addExtension(new CommonMarkCoreExtension());
            $environment->addExtension(new TableExtension());
            $environment->addExtension(new AutolinkExtension());
            $environment->addExtension(new TaskListExtension());
            $environment->addExtension(new AttributesExtension());
            self::$converter = new MarkdownConverter($environment);
        }
        return self::$converter;
    }

    public static function toHtml(string $markdown): string
    {
        return (string)self::converter()->convert($markdown);
    }
}
