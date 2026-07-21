# @newco/config

Shared presets and the design-token source of truth.

- `tokens/` — W3C design-token JSON (colour, type, spacing, radii, motion). Single source feeding the SPA's Tailwind preset **and** the Filament theme (design plan §3.1). One change propagates to every surface.
- `eslint/`, `tsconfig/` — shared lint and TypeScript presets.

Tokens are seeded in Sprint 1 alongside the design-system kickoff.
