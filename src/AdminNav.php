<?php

/**
 * Builds the teacher-panel sidebar for the current request.
 *
 * The sidebar has two levels: the global teacher menu, and - once a class is
 * selected (any /admin/classes/{slug}/... URL) - the options within that class.
 */
final class AdminNav
{
    /** Material Design icon paths (24px viewBox). */
    private const ICONS = [
        'school' => 'M5 13.18v4L12 21l7-3.82v-4L12 17l-7-3.82zM12 3L1 9l11 6 9-4.91V17h2V9L12 3z',
        'arrow_back' => 'M20 11H7.83l5.59-5.59L12 4l-8 8 8 8 1.41-1.41L7.83 13H20v-2z',
        'menu_book' => 'M21 5c-1.11-.35-2.33-.5-3.5-.5-1.95 0-4.05.4-5.5 1.5-1.45-1.1-3.55-1.5-5.5-1.5S2.45 4.9 1 6v14.65c0 .25.25.5.5.5.1 0 .15-.05.25-.05C3.1 20.45 5.05 20 6.5 20c1.95 0 4.05.4 5.5 1.5 1.35-.85 3.8-1.5 5.5-1.5 1.65 0 3.35.3 4.75 1.05.1.05.15.05.25.05.25 0 .5-.25.5-.5V6c-.6-.45-1.25-.75-2-1zm0 13.5c-1.1-.35-2.3-.5-3.5-.5-1.7 0-4.15.65-5.5 1.5V8c1.35-.85 3.8-1.5 5.5-1.5 1.2 0 2.4.15 3.5.5v11.5z',
        'assignment' => 'M19 3h-4.18C14.4 1.84 13.3 1 12 1c-1.3 0-2.4.84-2.82 2H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zm-7 0c.55 0 1 .45 1 1s-.45 1-1 1-1-.45-1-1 .45-1 1-1zm2 14H7v-2h7v2zm3-4H7v-2h10v2zm0-4H7V7h10v2z',
        'library_books' => 'M4 6H2v14c0 1.1.9 2 2 2h14v-2H4V6zm16-4H8c-1.1 0-2 .9-2 2v12c0 1.1.9 2 2 2h12c1.1 0 2-.9 2-2V4c0-1.1-.9-2-2-2zm-1 9H9V9h10v2zm-4 4H9v-2h6v2zm4-8H9V5h10v2z',
        'person' => 'M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z',
        'logout' => 'M17 7l-1.41 1.41L18.17 11H8v2h10.17l-2.58 2.58L17 17l5-5zM4 5h8V3H4c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h8v-2H4V5z',
        'open_in_new' => 'M19 19H5V5h7V3H5c-1.11 0-2 .9-2 2v14c0 1.1.89 2 2 2h14c1.1 0 2-.9 2-2v-7h-2v7zM14 3v2h3.59l-9.83 9.83 1.41 1.41L19 6.41V10h2V3h-7z',
        'menu' => 'M3 18h18v-2H3v2zm0-5h18v-2H3v2zm0-7v2h18V6H3z',
        'close' => 'M19 6.41L17.59 5 12 10.59 6.41 5 5 6.41 10.59 12 5 17.59 6.41 19 12 13.41 17.59 19 19 17.59 13.41 12z',
        'more_vert' => 'M12 8c1.1 0 2-.9 2-2s-.9-2-2-2-2 .9-2 2 .9 2 2 2zm0 2c-1.1 0-2 .9-2 2s.9 2 2 2 2-.9 2-2-.9-2-2-2zm0 6c-1.1 0-2 .9-2 2s.9 2 2 2 2-.9 2-2-.9-2-2-2z',
        'attach_file' => 'M16.5 6v11.5c0 2.21-1.79 4-4 4s-4-1.79-4-4V5c0-1.38 1.12-2.5 2.5-2.5s2.5 1.12 2.5 2.5v10.5c0 .55-.45 1-1 1s-1-.45-1-1V6H10v9.5c0 1.38 1.12 2.5 2.5 2.5s2.5-1.12 2.5-2.5V5c0-2.21-1.79-4-4-4S7 2.79 7 5v12.5c0 3.04 2.46 5.5 5.5 5.5s5.5-2.46 5.5-5.5V6h-1.5z',
    ];

    public static function icon(string $name): string
    {
        $path = self::ICONS[$name] ?? '';
        return '<svg class="icon" viewBox="0 0 24 24" width="20" height="20" aria-hidden="true" focusable="false"><path fill="currentColor" d="' . $path . '"/></svg>';
    }

    /** True when the current request belongs to the logged-in teacher panel. */
    public static function isAdminPage(string $path): bool
    {
        return Auth::isLoggedIn() && ($path === '/admin' || str_starts_with($path, '/admin/')) && $path !== '/admin/login';
    }

    /**
     * @return array{
     *   sections: array<int, array{label: ?string, items: array<int, array<string, mixed>>}>,
     *   class: ?array{slug: string, title: string}
     * }
     */
    public static function build(string $path): array
    {
        $segments = array_map('rawurldecode', array_values(array_filter(explode('/', $path), fn($s) => $s !== '')));

        $classSlug = null;
        $classTitle = null;
        if (($segments[1] ?? null) === 'classes' && isset($segments[2])) {
            $title = Storage::getClassTitle($segments[2]);
            if ($title !== null) {
                $classSlug = $segments[2];
                $classTitle = $title;
            }
        }

        if ($classSlug === null) {
            $isClasses = $path === '/admin' || (($segments[1] ?? null) === 'classes');
            return [
                'class' => null,
                'sections' => [[
                    'label' => I18n::ui('Teacher panel'),
                    'items' => [
                        ['label' => I18n::ui('Classes'), 'href' => '/admin', 'icon' => 'school', 'active' => $isClasses],
                        ['label' => I18n::ui('Exam content library'), 'href' => '/admin/exam-contents', 'icon' => 'library_books', 'active' => ($segments[1] ?? null) === 'exam-contents'],
                    ],
                ]],
            ];
        }

        $base = '/admin/classes/' . rawurlencode($classSlug);
        $area = $segments[3] ?? '';
        $onExams = $area === 'exams';
        $onTopics = !$onExams;

        $topicItems = [];
        foreach (Storage::listTopics($classSlug) as $topic) {
            $topicItems[] = [
                'label' => $topic['title'],
                'href' => $base . '/topics/' . rawurlencode($topic['slug']),
                'active' => $area === 'topics' && ($segments[4] ?? null) === $topic['slug'],
            ];
        }

        return [
            'class' => ['slug' => $classSlug, 'title' => $classTitle],
            'sections' => [[
                'label' => null,
                'items' => [
                    ['label' => I18n::ui('Topics & materials'), 'href' => $base, 'icon' => 'menu_book', 'active' => $onTopics, 'children' => $topicItems],
                    ['label' => I18n::ui('Exams'), 'href' => $base . '/exams', 'icon' => 'assignment', 'active' => $onExams],
                    ['label' => I18n::ui('Class page'), 'href' => '/class/' . rawurlencode($classSlug), 'icon' => 'open_in_new', 'active' => false, 'external' => true],
                ],
            ], [
                'label' => I18n::ui('Teacher panel'),
                'items' => [
                    ['label' => I18n::ui('Exam content library'), 'href' => '/admin/exam-contents', 'icon' => 'library_books', 'active' => false],
                ],
            ]],
        ];
    }
}
