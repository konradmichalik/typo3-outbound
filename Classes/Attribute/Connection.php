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

namespace KonradMichalik\Typo3Outbound\Attribute;

use Attribute;
use DateTimeInterface;
use KonradMichalik\Typo3Outbound\Enum\KeyCase;
use KonradMichalik\Typo3Outbound\Format\{PlainJson, ResponseFormat};
use KonradMichalik\Typo3Outbound\Value\ValueSource;

/**
 * Connection.
 *
 * Marks the base interface of an API. Holds everything that belongs to the API
 * account: base URI, authentication, limits, format and safety settings. Resource
 * interfaces extend the base interface; a small API declares its endpoints on the
 * base interface directly.
 *
 * @api
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 */
#[Attribute(Attribute::TARGET_CLASS)]
final readonly class Connection
{
    /**
     * @param string|null                               $name                Defaults to the interface name without "Api", in snake_case
     * @param array<string, string|ValueSource>         $headers             Default headers sent with every request
     * @param list<string>                              $allowedHosts        Hosts the base URI and next-page links may point to; empty means the host of the base URI
     * @param bool                                      $allowPrivateNetwork Opt-out of the private network block, for intranet APIs
     * @param bool                                      $sendLanguage        Send the language of the current site as Accept-Language
     * @param int|null                                  $maxResponseBytes    Overrides the global response size limit
     * @param bool                                      $verifyTls           Only effective in the Development context
     * @param array{remaining?: string, reset?: string} $rateLimitHeaders    Response headers announcing the remaining quota
     * @param bool                                      $permissiveTypes     Allows `mixed` in mapped types, for responses handed on as raw arrays
     */
    public function __construct(
        public string|ValueSource $baseUri,
        public ?string $name = null,
        public array $headers = [],
        public KeyCase $keyCase = KeyCase::None,
        public ResponseFormat $format = new PlainJson(),
        public array $allowedHosts = [],
        public bool $allowPrivateNetwork = false,
        public bool $sendLanguage = true,
        public string $dateFormat = DateTimeInterface::ATOM,
        public bool $booleansAsStrings = true,
        public ?int $maxResponseBytes = null,
        public bool $verifyTls = true,
        public string $idempotencyHeader = 'Idempotency-Key',
        public bool $quoteIdempotencyKey = true,
        public array $rateLimitHeaders = ['remaining' => 'X-RateLimit-Remaining', 'reset' => 'X-RateLimit-Reset'],
        public bool $permissiveTypes = false,
    ) {}
}
