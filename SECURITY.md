# Security Policy

## Supported versions

Only the latest minor release receives security fixes.

## Reporting a vulnerability

Please do not open public issues for security problems. Email
codearachnid@gmail.com with the details and steps to reproduce; you will
receive a response within a few business days.

## Handling secrets

- API keys and bearer tokens are marked `#[\SensitiveParameter]` so they are
  redacted from stack traces.
- The SDK never logs request or response bodies. If you add logging around it,
  scrub `apiKey`, `token`, bank account fields and SSNs first.
- Token stores persist bearer tokens; back them with storage that is
  appropriately protected (e.g. an encrypted cache) in shared environments.
