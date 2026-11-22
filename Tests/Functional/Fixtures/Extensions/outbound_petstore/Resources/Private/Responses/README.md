# Petstore responses

Modelled on the examples and schemas of `Tests/Fixtures/Petstore/openapi.yaml`
(swagger-api/swagger-petstore, version 1.0.30-SNAPSHOT). They are not recordings:
the public instance is shared and its data changes constantly.
`Tests/Integration/PetstoreLiveTest.php` runs the same interfaces against the real
API when `OUTBOUND_LIVE=1` is set.
