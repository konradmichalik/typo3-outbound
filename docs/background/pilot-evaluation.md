# Pilot evaluation

The MVP is accepted when one real integration runs in production through typo3-outbound without own HTTP code. This template makes that decision measurable, and with an optional Saloon variant also the choice between a dedicated runtime (option A) and a TYPO3 bridge for Saloon (option B).

## Setup

- **Integration:** an existing service wrapper from the [wrapper audit](wrapper-audit.md), ideally with reads, at least one write and authentication
- **Variants:** the existing wrapper (baseline), typo3-outbound, optionally Saloon with a minimal TYPO3 bridge
- **Same scope for all variants:** same endpoints, same behaviour, same tests

## Measurements

| Metric | Baseline | typo3-outbound | Saloon + bridge | How to measure |
|--------|----------|----------------|-----------------|----------------|
| Lines of code (integration incl. DTOs) | | | | `cloc` on the integration's files |
| Lines of code per additional endpoint | | | | add one endpoint, diff |
| PHPStan level max without `@var` | | | | `composer cgl sca` |
| Test setup lines per test | | | | count |
| Page cache carries API tags | | | | inspect cache_pages tags for a page using the data |
| Write invalidates affected pages | | | | update, reload page |
| Behaviour on 503 / timeout | | | | stop the API or point to a slow mock |
| Behaviour on 429 with Retry-After: 60 | | | | mock response |
| Secrets outside Git and TypoScript | | | | review |
| Request overhead | | | | `outbound:call` timing, profiler |
| Time to implement | | | | hours |
| Open issues found in production (4 weeks) | | | | issue tracker |

## Decision

- **Accept the MVP** when the integration runs in production for four weeks without own HTTP code and without open blockers.
- **Option A vs. B:** if typo3-outbound is clearly better in lines per endpoint, typing and web-request behaviour, keep the dedicated runtime. If the differences are small and only the TYPO3 integration (page cache, site credentials) matters, a bridge for Saloon is the cheaper long-term choice.
- **Record the result** in `docs/background/README.md` under "Comparison", with the numbers.
