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

namespace KonradMichalik\Typo3Outbound\Tests\Unit\Fixtures;

use KonradMichalik\Typo3Outbound\Attribute as Outbound;
use KonradMichalik\Typo3Outbound\Value\{Env, FilePart};

final readonly class DocumentDto
{
    public function __construct(public int $id, public string $title = '') {}
}

#[Outbound\Connection(baseUri: 'https://docs.example.com/api')]
#[Outbound\Auth\OAuth2ClientCredentials(tokenUrl: 'https://auth.example.com/token', clientId: new Env('OUTBOUND_TEST_CLIENT_ID'), clientSecret: new Env('OUTBOUND_TEST_CLIENT_SECRET'), scope: 'documents')]
interface DocumentsApi
{
    #[Outbound\Get('/documents/{id}')]
    #[Outbound\Cache(lifetime: 60)]
    public function find(int $id): DocumentDto;

    #[Outbound\Put('/documents/{id}', ifMatch: true)]
    public function update(int $id, #[Outbound\Body] DocumentDto $document): DocumentDto;

    /**
     * @param array<string, string|FilePart> $parts
     */
    #[Outbound\Post('/documents/upload')]
    public function upload(#[Outbound\Multipart] array $parts): DocumentDto;
}
