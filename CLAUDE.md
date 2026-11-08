# inertia-typo3

## Versioning

- SemVer, currently 0.x: breaking changes are accepted at this stage and a breaking commit is marked `feat!:` / `fix!:`. The repo has no `breaking` label.
- A release is a `chore: release X.Y.Z` commit on `main` that sets the version in `composer.json` (`extra.typo3/cms.version`) and `ext_emconf.php` (`tailor set-version X.Y.Z`), then an annotated tag `vX.Y.Z` on that commit. TER only takes three-part versions. Steps are in CONTRIBUTING.md.
- Releases happen only via `v*` tags. Packagist picks up the tag by itself, and `.github/workflows/publish.yml` builds the artefact with tailor and uploads it to the TYPO3 Extension Repository (needs the `TYPO3_API_TOKEN` secret). The workflow fails if the tag and the two version fields disagree.
- The tag message becomes the TER upload comment. GitHub release notes are written by hand for each tag; they are not generated.
- Never tag a version Packagist or TER already has, and never move or reuse a pushed tag.
