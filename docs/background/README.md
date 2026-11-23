# Background

Architecture, security model and the comparison with Saloon are described [in the core package](https://github.com/konradmichalik/php-outbound/blob/main/docs/background/README.md).

## Performance

The runtime adds about 0.18 ms per uncached call and 0.12 ms per cache hit (`OverheadTest` in the core, mock transport). In a TYPO3 instance, `ddev benchmark` measures the Petstore plugin with fixture responses (50 requests, median):

| Scenario | TYPO3 13 | TYPO3 14 |
|----------|----------|----------|
| Page cache hit | 5 queries | 5 queries |
| Response cache hit, uncached page | 10.1 ms, 6 queries | 15.4 ms, 7 queries |
| Response cache flushed before each request | 11.6 ms, 12 queries | 16.8 ms, 13 queries |

A response cache hit costs one query; a miss adds about 1.5 ms and six queries, the network call excluded. Times start at the profiler middleware, without the TYPO3 bootstrap. Shared state is read sparingly, because every access is a cache backend query: the circuit breaker once per call, announced quotas at most every five seconds, an OAuth token once per process.
