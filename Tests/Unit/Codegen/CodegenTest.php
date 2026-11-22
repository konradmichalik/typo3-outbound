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

namespace KonradMichalik\Typo3Outbound\Tests\Unit\Codegen;

use KonradMichalik\Typo3Outbound\Codegen\{DtoGenerator, OpenApiGenerator};
use KonradMichalik\Typo3Outbound\Metadata\Registry;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;
use Symfony\Component\Yaml\Yaml;

/**
 * CodegenTest.
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 */
#[CoversClass(OpenApiGenerator::class)]
#[CoversClass(DtoGenerator::class)]
final class CodegenTest extends TestCase
{
    #[Test]
    public function petstoreSpecGeneratesAValidApi(): void
    {
        $spec = Yaml::parseFile(__DIR__.'/../../Fixtures/Petstore/openapi.yaml');
        $namespace = 'Generated\\Run'.bin2hex(random_bytes(4));
        $files = (new OpenApiGenerator())->generate($spec, $namespace, 'Petstore');
        $directory = sys_get_temp_dir().'/outbound-codegen-'.bin2hex(random_bytes(4));
        foreach ($files as $path => $code) {
            @mkdir(dirname($directory.'/'.$path), 0o777, true);
            file_put_contents($directory.'/'.$path, $code);
        }
        spl_autoload_register(static function (string $class) use ($namespace, $directory): void {
            if (str_starts_with($class, $namespace.'\\')) {
                require $directory.'/'.str_replace('\\', '/', substr($class, strlen($namespace) + 1)).'.php';
            }
        });

        $registry = Registry::build([$namespace.'\\Api\\PetApi', $namespace.'\\Api\\StoreApi', $namespace.'\\Api\\UserApi']);

        self::assertSame(['petstore'], $registry->connectionNames());
        self::assertSame('/pet/findByStatus', $registry->endpoint($namespace.'\\Api\\PetApi', 'findPetsByStatus')->path);
        self::assertSame('list<'.$namespace.'\\Dto\\Pet>', $registry->endpoint($namespace.'\\Api\\PetApi', 'findPetsByStatus')->returnType->signature);
        self::assertSame('array<string, int>', $registry->endpoint($namespace.'\\Api\\StoreApi', 'getInventory')->returnType->signature);
        self::assertStringContainsString("new Secret('petstore.apiKey')", $files['Api/PetstoreApi.php']);
    }

    #[Test]
    public function sampleResponseGeneratesNestedDtos(): void
    {
        $files = (new DtoGenerator())->generate('Vendor\\Dto', 'ContactDto', ['id' => 1, 'full_name' => 'Ada', 'address' => ['city' => 'Dresden'], 'tags' => [['label' => 'vip']], 'notes' => null], camelCase: true);

        self::assertSame(['AddressDto', 'TagDto', 'ContactDto'], array_keys($files));
        self::assertStringContainsString('public string $fullName,', $files['ContactDto']);
        self::assertStringContainsString('@param list<TagDto> $tags', $files['ContactDto']);
        self::assertStringContainsString('public mixed $notes = null,', $files['ContactDto']);
    }

    #[Test]
    public function hostileSpecTextCannotBreakOutOfComments(): void
    {
        $payload = "x */ system('pwned'); /*";
        $spec = [
            'openapi' => '3.0.0',
            'info' => ['title' => $payload, 'version' => '1'],
            'servers' => [['url' => 'https://api.example.com']],
            'paths' => ['/things' => ['get' => [
                'operationId' => 'listThings',
                'summary' => $payload,
                'responses' => ['200' => ['description' => 'ok', 'content' => ['application/json' => ['schema' => ['$ref' => '#/components/schemas/Thing']]]]],
            ]]],
            'components' => [
                'securitySchemes' => ["bad\nsystem('pwned');//" => ['type' => 'mutualTLS']],
                'schemas' => ['Thing' => ['type' => 'object', 'description' => $payload, 'properties' => ['id' => ['type' => 'integer', 'description' => $payload]]]],
            ],
        ];

        $files = (new OpenApiGenerator())->generate($spec, 'Generated\\Hostile', 'Things');

        self::assertNotSame([], $files);
        foreach ($files as $path => $code) {
            $calls = array_filter(token_get_all($code), static fn(mixed $token): bool => is_array($token) && \T_STRING === $token[0] && 'system' === $token[1]);
            self::assertSame([], $calls, $path.' contains injected code.');
            self::assertStringNotContainsString('<?php', substr($code, 5), $path);
        }
    }
}
