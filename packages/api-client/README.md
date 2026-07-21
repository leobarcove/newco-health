# @newco/api-client

TypeScript client generated from the API's OpenAPI specification.

Do not hand-edit generated output. Workflow (dev plan §11):
1. Change the API → update the OpenAPI spec in `apps/api`.
2. Regenerate this package (CI regenerates and fails on drift).
3. `apps/web` consumes only this client — no ad-hoc `fetch` calls to the API.

Generation tooling is chosen in Sprint 1 (openapi-typescript + a thin fetch wrapper is the default candidate — zero runtime dependencies).
