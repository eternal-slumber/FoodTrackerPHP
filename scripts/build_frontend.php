<?php

declare(strict_types=1);

const FRONTEND_ROOT = __DIR__ . '/../resources/frontend';
const PUBLIC_ROOT = __DIR__ . '/../public/assets/app/dist';

$cssSources = [
    'app/css/base/tokens.css',
    'app/css/base/reset.css',
    'app/css/base/layout.css',
    'app/css/base/utilities.css',
    'app/css/screens/loading.css',
    'app/css/screens/welcome.css',
    'app/css/features/register/core.css',
    'app/css/features/register/steps.css',
    'app/css/features/register/form.css',
    'app/css/features/register/success.css',
    'app/css/screens/home.css',
    'app/css/components/cards.css',
    'app/css/features/summary/dashboard.css',
    'app/css/features/summary/screen.css',
    'app/css/features/history/list.css',
    'app/css/features/history/calendar.css',
    'app/css/features/history/screen.css',
    'app/css/features/history/details.css',
    'app/css/components/buttons.css',
    'app/css/components/tabbar.css',
    'app/css/features/profile/settings.css',
    'app/css/features/profile/profile.css',
    'app/css/features/profile/screen.css',
    'app/css/screens/profile-edit.css',
    'app/css/components/forms.css',
    'app/css/components/sheets.css',
    'app/css/features/meal-draft/core.css',
    'app/css/components/surfaces.css',
    'app/css/features/meal-draft/photo.css',
    'app/css/features/meal-draft/main-product.css',
    'app/css/features/meal-draft/product-editor.css',
    'app/css/features/meal-draft/actions.css',
    'app/css/features/meal-draft/shell.css',
    'app/css/features/meal-draft/products.css',
    'app/css/features/meal-draft/ai-states.css',
];

$jsSources = [
    'shared/js/helpers.js',
    'app/js/auth.js',
    'shared/js/http.js',
    'app/js/progress.js',
    'app/js/daily-insight.js',
    'app/js/features/history/data.js',
    'app/js/features/history/today.js',
    'app/js/features/history/list.js',
    'app/js/features/history/details.js',
    'app/js/features/history/support.js',
    'app/js/features/meal-draft/state.js',
    'app/js/features/meal-draft/sheet.js',
    'app/js/features/meal-draft/photo.js',
    'app/js/features/meal-draft/products-render.js',
    'app/js/features/meal-draft/products-draft.js',
    'app/js/features/meal-draft/products-interactions.js',
    'app/js/features/meal-draft/ai.js',
    'app/js/features/meal-draft/save.js',
    'app/js/features/meal-draft/bindings.js',
    'app/js/features/profile/profile.js',
    'app/js/features/onboarding/onboarding.js',
    'app/js/summary.js',
    'app/js/features/history/calendar-data.js',
    'app/js/features/history/calendar-view.js',
    'app/js/features/history/calendar-bindings.js',
    'app/js/app.js',
];

buildBundle($cssSources, PUBLIC_ROOT . '/app.css', 'css');
buildBundle($jsSources, PUBLIC_ROOT . '/app.js', 'js');

echo sprintf(
    "Frontend built: %d CSS modules, %d JS modules.\n",
    count($cssSources),
    count($jsSources)
);

/**
 * @param list<string> $sources
 */
function buildBundle(array $sources, string $output, string $type): void
{
    $bundle = '';

    foreach ($sources as $source) {
        $path = FRONTEND_ROOT . '/' . $source;
        $contents = file_get_contents($path);

        if ($contents === false) {
            throw new RuntimeException(sprintf('Unable to read frontend source "%s".', $source));
        }

        $label = $type === 'css'
            ? sprintf('/* Source: %s */', $source)
            : sprintf('// Source: %s', $source);

        $bundle .= $label . "\n" . rtrim($contents) . "\n\n";
    }

    $directory = dirname($output);
    if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) {
        throw new RuntimeException(sprintf('Unable to create frontend output directory "%s".', $directory));
    }

    $temporaryOutput = $output . '.tmp';
    if (file_put_contents($temporaryOutput, $bundle, LOCK_EX) === false) {
        throw new RuntimeException(sprintf('Unable to write frontend bundle "%s".', $output));
    }

    if (!rename($temporaryOutput, $output)) {
        throw new RuntimeException(sprintf('Unable to publish frontend bundle "%s".', $output));
    }
}
