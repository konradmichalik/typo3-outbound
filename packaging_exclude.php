<?php

declare(strict_types=1);

/*
 * This file is part of the "typo3_outbound" TYPO3 CMS extension.
 *
 * (c) 2026 Konrad Michalik <hej@konradmichalik.dev>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

return [
    'directories' => [
        '.build',
        '.ddev',
        '.git',
        '.github',
        'bin',
        'build',
        'docs',
        'public',
        'tailor-version-artefact',
        'tailor-version-upload',
        'tests',
        'var',
        'vendor',
    ],
    'files' => [
        'DS_Store',
        'AGENTS.md',
        'CLAUDE.md',
        'CODE_OF_CONDUCT.md',
        'CODEOWNERS',
        'composer.lock',
        'composer-require-checker.json',
        'composer-unused.php',
        'CONTRIBUTING.md',
        'editorconfig',
        'gitattributes',
        'gitignore',
        'packaging_exclude.php',
        'phpunit.result.cache',
        'php-cs-fixer.php',
        'phpstan-baseline.neon',
        'phpunit.functional.xml',
        'phpunit.xml',
        'phpstan.neon',
        'rector.php',
        'renovate.json',
        'SECURITY.md',
        'typo3-vendor-bundler.yaml',
        'version-bumper.yaml',
    ],
];
