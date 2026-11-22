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

use KonradMichalik\Typo3Outbound\Middleware\RequestContextMiddleware;

return [
    'frontend' => [
        'konradmichalik/typo3-outbound/request-context' => [
            'target' => RequestContextMiddleware::class,
            'after' => ['typo3/cms-frontend/site'],
        ],
    ],
    'backend' => [
        'konradmichalik/typo3-outbound/request-context' => [
            'target' => RequestContextMiddleware::class,
            'after' => ['typo3/cms-backend/site-resolver'],
        ],
    ],
];
