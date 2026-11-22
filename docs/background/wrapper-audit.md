# Wrapper audit

A one-hour inventory of the API integrations a project already has. It tells whether typo3-outbound would remove real problems or only rewrite working code. Fill one row per integration.

## How to find the integrations

- Search for `RequestFactory`, `GuzzleHttp\Client`, `HttpClientInterface`, `curl_init`, `file_get_contents('http`
- Look for services named `*Service`, `*Client`, `*Api`, `*Connector` in `Classes/`
- Ask the project lead which external systems the site depends on

## Questions per integration

| Question | Why it matters |
|----------|----------------|
| Which API, how many endpoints, read or write? | Size of a migration |
| Timeout set? Value? | Long timeouts block page rendering |
| Retries? Also for POST/PUT? | Repeated writes create duplicates |
| Response cache? Lifetime? Tags? | Missing tags force full cache flushes |
| Does the page cache know about the data? | Stale pages or disabled caching |
| Where do credentials live? | Secrets in Git or TypoScript |
| Different credentials per site? | Multi-site setups need site-aware config |
| How are failures handled? | Swallowed errors show empty content silently |
| Arrays or typed objects? | Renamed fields break at runtime |
| URL parameters encoded? | Path and query injection |
| Tests? With real API or mocks? | Fragile or missing tests |
| Lines of code of the integration | Baseline for the pilot comparison |

## Template

| Project | API | Endpoints (read/write) | Timeout | Retry | Cache / tags | Page cache aware | Credentials | Error handling | Types | Tests | LOC |
|---------|-----|------------------------|---------|-------|--------------|------------------|-------------|----------------|-------|-------|-----|
| | | | | | | | | | | | |

## Reading the result

- **Many rows with gaps in timeout, retry, page cache or credentials:** the potential is real; pick the integration with the most gaps and the most traffic as pilot.
- **Few integrations, cleanly built:** typo3-outbound is a nice-to-have for this project; check the next one before investing more.
- **Mostly official SDKs:** typo3-outbound adds little there; the SDK stays.
