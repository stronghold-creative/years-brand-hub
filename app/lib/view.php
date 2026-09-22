<?php
declare(strict_types=1);

function view(string $template, array $data = [], ?string $layout = 'layout'): void
{
    extract($data, EXTR_SKIP);
    ob_start();
    require APP_DIR . '/views/' . $template . '.php';
    $content = ob_get_clean();

    if ($layout === null) {
        echo $content;
        return;
    }
    require APP_DIR . '/views/' . $layout . '.php';
}

function partial(string $template, array $data = []): void
{
    extract($data, EXTR_SKIP);
    require APP_DIR . '/views/' . $template . '.php';
}
