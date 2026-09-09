<?php

declare(strict_types=1);

namespace frontend\shared\view;

final class View
{
    public function __construct(private readonly string $root)
    {
    }

    /**
     * @param array<string, mixed> $data
     */
    public function page(string $viewFile, array $data = []): string
    {
        $data['contentView'] = $this->root . '/' . $viewFile;
        extract($data, EXTR_SKIP);
        ob_start();
        include $this->root . '/shared/view/layout.php';
        return (string) ob_get_clean();
    }

    /**
     * Layout del nivel 1 (sin cinta del centro).
     *
     * @param array<string, mixed> $data
     */
    public function pageYo(string $viewFile, array $data = []): string
    {
        $data['contentView'] = $this->root . '/' . $viewFile;
        extract($data, EXTR_SKIP);
        ob_start();
        include $this->root . '/shared/view/layout_yo.php';

        return (string) ob_get_clean();
    }

    /**
     * @param array<string, mixed> $data
     */
    public function standalone(string $viewFile, array $data = []): string
    {
        extract($data, EXTR_SKIP);
        ob_start();
        include $this->root . '/' . $viewFile;
        return (string) ob_get_clean();
    }
}
