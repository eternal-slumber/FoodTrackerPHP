<?php

declare(strict_types=1);

namespace App\View;

use InvalidArgumentException;
use RuntimeException;

class ViewRenderer
{
    public function __construct(private readonly string $viewsPath) {}

    /**
     * @param array<string, mixed> $replacements
     */
    public function render(string $view, array $replacements = []): string
    {
        $template = $this->expandPartials($this->read($view), [$view]);

        return strtr($template, $this->placeholders($replacements));
    }

    private function read(string $view): string
    {
        $template = file_get_contents($this->resolvePath($view));
        if ($template === false) {
            throw new RuntimeException(sprintf('Unable to read view "%s".', $view));
        }

        return $template;
    }

    /**
     * @param list<string> $stack
     */
    private function expandPartials(string $template, array $stack): string
    {
        $expanded = preg_replace_callback(
            '/{{>\s*([^{}]+?)\s*}}/',
            function (array $matches) use ($stack): string {
                $partial = trim($matches[1]);

                if (in_array($partial, $stack, true)) {
                    throw new RuntimeException(sprintf(
                        'Circular view partial reference: %s.',
                        implode(' -> ', [...$stack, $partial])
                    ));
                }

                return $this->expandPartials(
                    $this->read($partial),
                    [...$stack, $partial]
                );
            },
            $template
        );

        if ($expanded === null) {
            throw new RuntimeException('Unable to expand view partials.');
        }

        return $expanded;
    }

    private function resolvePath(string $view): string
    {
        $normalized = ltrim(str_replace('\\', '/', $view), '/');

        if ($normalized === '' || str_contains($normalized, '..')) {
            throw new InvalidArgumentException(sprintf('Invalid view path "%s".', $view));
        }

        $path = rtrim($this->viewsPath, '/') . '/' . $normalized;
        if (!is_file($path)) {
            throw new RuntimeException(sprintf('View "%s" not found.', $view));
        }

        return $path;
    }

    /**
     * @param array<string, mixed> $replacements
     * @return array<string, string>
     */
    private function placeholders(array $replacements): array
    {
        $placeholders = [];

        foreach ($replacements as $key => $value) {
            $placeholders['{{' . $key . '}}'] = (string)$value;
        }

        return $placeholders;
    }
}
