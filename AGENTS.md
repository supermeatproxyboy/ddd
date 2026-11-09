# Project instructions

## Preserve formatting

- Preserve the user's line wrapping, spacing, indentation, alignment, brace placement, and import order when editing code.
- Do not run formatters or automatic fix commands unless the user explicitly requests formatting. This includes Laravel Pint, `vp check`, `vp check --fix`, `vp fmt`, Oxfmt, Prettier, and lint commands with `--fix`.
- Make targeted edits; do not reformat surrounding or unrelated code.
- Use read-only checks: `npm run check` runs `vp lint`, and `composer ci:check` runs lint, type checks, static analysis, and tests without enforcing formatting.
- Keep editor save-formatting and source actions on save disabled in the workspace.

## Explicit architecture

- Use explicit `Command` and `Query` suffixes for application operations, matching their filenames.
- Name injected dependencies for their role and preserve the user's explicit call-site conventions.
