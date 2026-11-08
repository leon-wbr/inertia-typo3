# inertia-typo3

## Versioning

- SemVer, currently 0.x: breaking changes bump the minor. A breaking PR has a `!` in its title (`feat!: …`) and the `breaking` label; the PR-title check fails if only one of the two is there.
- A release is a `chore: release X.Y.Z` commit on `main` that sets the version in `composer.json` (`extra.typo3/cms.version`) and `ext_emconf.php` (`tailor set-version X.Y.Z`), then an annotated tag `vX.Y.Z` on that commit. TER only takes three-part versions. Steps are in CONTRIBUTING.md.
- Releases happen only via `v*` tags. Packagist picks up the tag by itself. `.github/workflows/publish.yml` checks that the tagged commit is on main and that the tag matches both version fields, re-runs `ddev verify` on the tag, then waits for approval in the `ter` environment (which holds `TYPO3_API_TOKEN`) before tailor uploads to TER.
- The tag message becomes the TER upload comment. GitHub release notes are written by hand for each tag.
- `v*` tags are immutable (tag ruleset): never tag a version Packagist or TER already has, and never move or reuse a pushed tag.
