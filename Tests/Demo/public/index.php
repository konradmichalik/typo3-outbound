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

/*
 * Petstore test frontend, no TYPO3 needed:
 *
 *   php -S localhost:8080 -t Tests/Demo/public
 *
 * Calls https://petstore3.swagger.io live. Append ?mock=1 to answer from the
 * fixture responses instead. Cache and circuit breaker state live in the
 * system temp directory and survive page loads.
 */

use KonradMichalik\OutboundPetstore\Api\{PetApi, StoreApi};
use KonradMichalik\OutboundPetstore\Dto\{CategoryDto, PetDto, PetStatus};
use KonradMichalik\OutboundPetstore\Transport\MockPetstore;
use KonradMichalik\Typo3Outbound\Context\SapiExecutionContext;
use KonradMichalik\Typo3Outbound\Exception\ApiException;
use KonradMichalik\Typo3Outbound\Outbound;
use KonradMichalik\Typo3Outbound\Proxy\FileProxyStore;
use KonradMichalik\Typo3Outbound\Tests\Demo\{FileResponseCache, FileStateStore, RecordingTransport};
use KonradMichalik\Typo3Outbound\Transport\SymfonyTransport;

require getenv('OUTBOUND_AUTOLOAD') ?: dirname(__DIR__, 3).'/vendor/autoload.php';

$mock = '1' === ($_GET['mock'] ?? getenv('OUTBOUND_DEMO_MOCK'));
$storage = sys_get_temp_dir().'/typo3-outbound-demo/'.($mock ? 'mock' : 'live');
$transport = new RecordingTransport($mock ? MockPetstore::create() : SymfonyTransport::create());
$outbound = Outbound::create([PetApi::class, StoreApi::class])
    ->withTransport($transport)
    ->withExecutionContext(new SapiExecutionContext(web: true))
    ->withCache(new FileResponseCache($storage.'/cache'))
    ->withStateStore(new FileStateStore($storage.'/state'))
    ->withProxyStore(new FileProxyStore($storage.'/proxies'));
$pets = $outbound->api(PetApi::class);
$store = $outbound->api(StoreApi::class);

$page = (string) ($_GET['page'] ?? 'pets');
$link = static fn(array $query = []): string => '?'.http_build_query([...$query, ...($mock ? ['mock' => 1] : [])]);
$e = static fn(mixed $value): string => htmlspecialchars((string) $value, ENT_QUOTES);

$content = '';
$error = null;
$started = microtime(true);
try {
    $content = match ($page) {
        'pet' => renderPet($pets->find((int) ($_GET['id'] ?? 0)), $e),
        'add' => 'POST' === $_SERVER['REQUEST_METHOD'] ? renderPet($pets->add(new PetDto(
            name: (string) ($_POST['name'] ?? ''),
            photoUrls: array_values(array_filter([(string) ($_POST['photoUrl'] ?? '')])),
            id: random_int(1_000_000, 9_000_000),
            category: '' !== ($_POST['category'] ?? '') ? new CategoryDto(1, (string) $_POST['category']) : null,
            status: PetStatus::tryFrom((string) ($_POST['status'] ?? '')) ?? PetStatus::Available,
        )), $e, 'Pet created') : renderAddForm($link, $e),
        'inventory' => renderInventory($store->inventory(), $e),
        'order' => renderOrder(isset($_GET['id']) ? $store->order((int) $_GET['id']) : false, $link, $e),
        default => renderPets($pets->findByStatus(PetStatus::tryFrom((string) ($_GET['status'] ?? '')) ?? PetStatus::Available), (string) ($_GET['status'] ?? 'available'), $link, $e),
    };
} catch (ApiException $exception) {
    $error = $exception;
}
$duration = (microtime(true) - $started) * 1000;

function renderPets(array $list, string $status, Closure $link, Closure $e): string
{
    $options = '';
    foreach (PetStatus::cases() as $case) {
        $options .= sprintf('<option value="%s"%s>%s</option>', $case->value, $case->value === $status ? ' selected' : '', $case->value);
    }
    $rows = '';
    foreach (array_slice($list, 0, 50) as $pet) {
        $rows .= sprintf(
            '<tr><td><a href="%s">%s</a></td><td>%s</td><td>%s</td><td>%s</td><td>%s</td></tr>',
            $e($link(['page' => 'pet', 'id' => $pet->id])),
            $e($pet->id),
            $e($pet->name),
            $e($pet->category?->name ?? '–'),
            $e(implode(', ', array_filter(array_map(static fn($tag) => $tag->name, $pet->tags))) ?: '–'),
            $e($pet->status?->value ?? '–'),
        );
    }

    return sprintf(
        '<h1>Pets</h1><form method="get">%s<label>Status <select name="status" onchange="this.form.submit()">%s</select></label></form>
        <p class="muted">%d pets%s. <code>PetApi::findByStatus</code> is cached for 30 seconds.</p>
        <table><thead><tr><th>ID</th><th>Name</th><th>Category</th><th>Tags</th><th>Status</th></tr></thead><tbody>%s</tbody></table>',
        str_contains($link(), 'mock') ? '<input type="hidden" name="mock" value="1">' : '',
        $options,
        count($list),
        count($list) > 50 ? ', showing the first 50' : '',
        $rows ?: '<tr><td colspan="5">No pets.</td></tr>',
    );
}

function renderPet(?PetDto $pet, Closure $e, string $title = 'Pet'): string
{
    if (null === $pet) {
        return '<h1>Pet</h1><p>Not found. <code>#[OnStatus(404, returns: null)]</code> turned the 404 into <code>null</code>.</p>';
    }
    $photos = implode('', array_map(static fn(string $url): string => '<li>'.$e($url).'</li>', $pet->photoUrls));

    return sprintf(
        '<h1>%s</h1><dl><dt>ID</dt><dd>%s</dd><dt>Name</dt><dd>%s</dd><dt>Category</dt><dd>%s</dd><dt>Status</dt><dd>%s</dd><dt>Photo URLs</dt><dd><ul>%s</ul></dd></dl>
        <p class="muted"><code>PetApi::find</code> is cached for 60 seconds and served stale for 5 minutes if the API fails.</p>',
        $e($title),
        $e($pet->id),
        $e($pet->name),
        $e($pet->category?->name ?? '–'),
        $e($pet->status?->value ?? '–'),
        $photos ?: '<li>–</li>',
    );
}

function renderAddForm(Closure $link, Closure $e): string
{
    $options = implode('', array_map(static fn(PetStatus $case): string => sprintf('<option>%s</option>', $case->value), PetStatus::cases()));

    return sprintf(
        '<h1>Add a pet</h1><form method="post" action="%s" class="stack">
        <label>Name <input name="name" required value="typo3-outbound-demo"></label>
        <label>Category <input name="category" value="Dogs"></label>
        <label>Photo URL <input name="photoUrl" type="url" value="https://example.org/pet.jpg"></label>
        <label>Status <select name="status">%s</select></label>
        <button>Add pet</button></form>
        <p class="muted"><code>PetApi::add</code> is a POST: it is never retried automatically.</p>',
        $e($link(['page' => 'add'])),
        $options,
    );
}

/**
 * @param array<string, int> $inventory
 */
function renderInventory(array $inventory, Closure $e): string
{
    $rows = '';
    foreach ($inventory as $status => $count) {
        $rows .= sprintf('<tr><td>%s</td><td class="number">%s</td></tr>', $e($status), $e($count));
    }

    return '<h1>Store inventory</h1><table><thead><tr><th>Status</th><th class="number">Count</th></tr></thead><tbody>'.$rows.'</tbody></table>';
}

function renderOrder(mixed $order, Closure $link, Closure $e): string
{
    $form = sprintf('<h1>Order lookup</h1><form method="get" class="inline">%s<input type="hidden" name="page" value="order"><label>Order ID <input name="id" type="number" min="1" value="%s"></label><button>Look up</button></form>', str_contains($link(), 'mock') ? '<input type="hidden" name="mock" value="1">' : '', $e($_GET['id'] ?? 5));
    if (false === $order) {
        return $form;
    }
    if (null === $order) {
        return $form.'<p>No such order.</p>';
    }

    return $form.sprintf('<dl><dt>ID</dt><dd>%s</dd><dt>Pet</dt><dd>%s</dd><dt>Quantity</dt><dd>%s</dd><dt>Ship date</dt><dd>%s</dd><dt>Status</dt><dd>%s</dd></dl>', $e($order->id), $e($order->petId), $e($order->quantity), $e($order->shipDate?->format('Y-m-d H:i') ?? '–'), $e($order->status?->value ?? '–'));
}

$log = '';
foreach ($transport->attempts as $attempt) {
    $status = 'error';
    try {
        $status = (string) $attempt['response']->getStatusCode();
    } catch (Throwable) {
    }
    $log .= sprintf('<tr><td>%s</td><td>%s</td><td><code>%s</code></td><td>%s</td></tr>', $e($attempt['endpoint']), $e($attempt['method']), $e($attempt['url']), $e($status));
}
$nav = '';
foreach (['pets' => 'Pets', 'add' => 'Add pet', 'inventory' => 'Inventory', 'order' => 'Orders'] as $key => $label) {
    $nav .= sprintf('<a href="%s"%s>%s</a>', $e($link(['page' => $key])), $key === $page ? ' aria-current="page"' : '', $label);
}
?><!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>typo3-outbound · Petstore</title>
<style>
:root { --bg: #f7f7f5; --surface: #fff; --text: #1d1d1b; --muted: #6b6b66; --line: #e2e2dc; --accent: #2457c5; --error-bg: #fdecea; --error: #8a1c12; color-scheme: light; }
@media (prefers-color-scheme: dark) { :root { --bg: #161615; --surface: #1f1f1d; --text: #ececea; --muted: #a0a09a; --line: #34342f; --accent: #8fb0ff; --error-bg: #3b1a17; --error: #ffb4a9; color-scheme: dark; } }
* { box-sizing: border-box; }
body { margin: 0; font: 15px/1.5 system-ui, -apple-system, "Segoe UI", sans-serif; background: var(--bg); color: var(--text); }
header { display: flex; flex-wrap: wrap; gap: 1rem; align-items: center; justify-content: space-between; padding: 1rem 1.5rem; background: var(--surface); border-bottom: 1px solid var(--line); }
header strong { font-size: 1.05rem; }
nav { display: flex; gap: .25rem; flex-wrap: wrap; }
nav a { padding: .35rem .7rem; border-radius: 6px; color: var(--text); text-decoration: none; }
nav a[aria-current] { background: var(--accent); color: var(--surface); }
.mode a { color: var(--accent); }
main { max-width: 960px; margin: 0 auto; padding: 1.5rem; }
h1 { font-size: 1.4rem; margin: 0 0 1rem; }
h2 { font-size: 1rem; margin: 2rem 0 .5rem; }
table { width: 100%; border-collapse: collapse; background: var(--surface); border: 1px solid var(--line); border-radius: 8px; overflow: hidden; }
th, td { text-align: left; padding: .5rem .75rem; border-bottom: 1px solid var(--line); vertical-align: top; }
th { font-weight: 600; color: var(--muted); font-size: .85rem; }
.number { text-align: right; font-variant-numeric: tabular-nums; }
.scroll { overflow-x: auto; }
a { color: var(--accent); }
.muted { color: var(--muted); font-size: .9rem; }
code { font: .85em ui-monospace, SFMono-Regular, Menlo, monospace; word-break: break-all; }
dl { display: grid; grid-template-columns: max-content 1fr; gap: .4rem 1.5rem; background: var(--surface); border: 1px solid var(--line); border-radius: 8px; padding: 1rem 1.25rem; }
dt { color: var(--muted); } dd { margin: 0; } dd ul { margin: 0; padding-left: 1.1rem; }
form.stack { display: grid; gap: .75rem; max-width: 420px; }
form.inline { display: flex; gap: .75rem; align-items: end; flex-wrap: wrap; margin-bottom: 1rem; }
label { display: grid; gap: .25rem; font-size: .9rem; color: var(--muted); }
input, select, button { font: inherit; padding: .45rem .6rem; border: 1px solid var(--line); border-radius: 6px; background: var(--surface); color: var(--text); }
button { background: var(--accent); color: var(--surface); border: 0; cursor: pointer; justify-self: start; }
.error { background: var(--error-bg); color: var(--error); padding: 1rem 1.25rem; border-radius: 8px; }
</style>
</head>
<body>
<header>
  <strong>typo3-outbound · Petstore</strong>
  <nav><?= $nav ?></nav>
  <span class="mode muted"><?= $mock ? 'Fixture responses · <a href="?page='.$e($page).'">switch to live</a>' : 'Live: petstore3.swagger.io · <a href="?page='.$e($page).'&amp;mock=1">switch to fixtures</a>' ?></span>
</header>
<main>
<?php if (null !== $error): ?>
  <div class="error"><strong><?= $e((new ReflectionClass($error))->getShortName()) ?></strong>: <?= $e($error->getMessage()) ?><br><span>Endpoint <?= $e($error->endpoint) ?>, status <?= $e($error->status ?? '–') ?></span></div>
<?php else: ?>
  <?= $content ?>
<?php endif ?>
  <h2>Requests sent for this page</h2>
  <p class="muted"><?= count($transport->attempts) ?> requests in <?= $e(number_format($duration, 1)) ?> ms. No row means the answer came from the cache.</p>
  <div class="scroll"><table><thead><tr><th>Endpoint</th><th>Method</th><th>URL</th><th>Status</th></tr></thead><tbody><?= $log ?: '<tr><td colspan="4">None.</td></tr>' ?></tbody></table></div>
</main>
</body>
</html>
