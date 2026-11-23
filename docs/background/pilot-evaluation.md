# Pilot evaluation

The MVP is accepted when one real integration runs in production through typo3-outbound without own HTTP code. This template makes that decision measurable, and with an optional Saloon variant also the choice between a dedicated runtime (option A) and a TYPO3 bridge for Saloon (option B).

## Setup

- **Integration:** an existing service wrapper from the [wrapper audit](wrapper-audit.md), ideally with reads, at least one write and authentication
- **Variants:** the existing wrapper (baseline), typo3-outbound, optionally Saloon with a minimal TYPO3 bridge
- **Same scope for all variants:** same endpoints, same behaviour, same tests

## Measurements

| Metric | Baseline | typo3-outbound | Saloon + bridge | How to measure |
|--------|----------|----------------|-----------------|----------------|
| Lines of code (integration incl. DTOs) | 292 in 11 classes (client 144), tests 397 | 149 in 8 classes (interface and DTO 41, adapter 36), tests 279 | | `cloc` on the integration's files |
| Lines of code per additional endpoint | about 8 (public method plus path helper) | about 4 (attribute and interface method), plus a pass-through in the adapter if the sync needs it | | add one endpoint, diff |
| PHPStan level max without `@var` | level 6 in the project, no `@var` | level 6 in the project, no `@var`; the extension itself runs on level max | | `composer cgl sca` |
| Test setup lines per test | 61 shared lines (Guzzle mock stack, cache and throttle stubs) for 16 tests | 9 lines (`MockTransport`, endpoint based) plus a 40-line trait that replaces the `.env.local` values, shared by 12 tests | | count |
| Page cache carries API tags | n/a, the sync renders no pages | | | inspect cache_pages tags for a page using the data |
| Write invalidates affected pages | n/a, read only | | | update, reload page |
| Behaviour on 503 / timeout | exception, no retry, sync keeps the last state | outbound exception, no retry (no `#[Retry]` declared), sync keeps the last state | | stop the API or point to a slow mock |
| Behaviour on 429 with Retry-After: 60 | exception, header ignored | not measured | | mock response |
| Secrets outside Git and TypoScript | yes, environment variables | yes, `Env` values; login body and homepage token never reach the log (tested) | | review |
| Request overhead | not measured | not measured; full media sync of 14 estates 72 s, regular sync 2.4 s, both at 3 requests per second | | `outbound:call` timing, profiler |
| Time to implement | unknown | open, to be filled in by Konrad | | hours |
| Open issues found in production (4 weeks) | open | | | issue tracker |

Baseline of the first pilot: the Immomio sync of the Neuland website (`packages/immomio`), login with user and password, paged estate search, media list and download. Lines counted without blank and comment lines, `cloc` was not available.

## Findings of the Immomio pilot

| Gap | Status |
|-----|--------|
| Login with user and password for a bearer token | closed: `#[Auth\TokenLogin]` |
| Optional environment variable with a default (`IMMOMIO_BASE_URL`) | closed: `new Env('NAME', default: …)` |
| Raw arrays (`array<string, mixed>`) handed on to existing code | closed: `#[Connection(permissiveTypes: true)]`, strict stays the default |
| CLI gave up on a burst with `symfony/rate-limiter` below 7.4.22, the reported retry time had already passed | closed: requires `^7.4.23`, burst test with a real clock |
| Connect timeout separate from the total timeout | open: Symfony HttpClient has no option and blocks `CURLOPT_CONNECTTIMEOUT(_MS)` in `extra.curl`; the connection only declares `#[Timeout(30)]` |
| Rate limits below one second (the former client kept 0.3 s between requests) | open: Symfony's rate limiter counts whole seconds, the pilot uses three requests per second, which allows a burst of three |
| Only the first `#[Body]` parameter becomes the body | open, by design: the search takes one array shape parameter |

## Decision

- **Accept the MVP** when the integration runs in production for four weeks without own HTTP code and without open blockers.
- **Option A vs. B:** if typo3-outbound is clearly better in lines per endpoint, typing and web-request behaviour, keep the dedicated runtime. If the differences are small and only the TYPO3 integration (page cache, site credentials) matters, a bridge for Saloon is the cheaper long-term choice.
- **Record the result** in `docs/background/README.md` under "Comparison", with the numbers.
