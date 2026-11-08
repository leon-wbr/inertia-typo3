# Contributing to TYPO3 Inertia Integration

We're in early development and welcome all contributions! The project aims to follow the official InertiaJS Laravel adapter as much as possible, adapting only what's necessary for TYPO3.

## How to Contribute

1. Fork and clone the repository
2. Make your changes
3. Run `ddev verify` (see the README) and make sure it passes
4. Submit a pull request

That's it! No extensive process, no strict rules. Do-ocracy.

## Recommendations

- Follow the [official Inertia.js Laravel adapter](https://github.com/inertiajs/inertia-laravel) patterns where possible  
- Follow TYPO3 standards when interacting with TYPO3
- If you're not sure about something, just ask in your PR or in an issue
- Breaking changes are fine at this stage - we're figuring things out

## Releasing

A release is a version tag. Packagist reads the tag, and the [publish workflow](.github/workflows/publish.yml) uploads the same version to the TYPO3 Extension Repository (TER) with [tailor](https://github.com/TYPO3/tailor).

1. Set the version in `composer.json` (`extra.typo3/cms.version`) and `ext_emconf.php`: `tailor set-version 1.2.3`, or edit both by hand.
2. Commit it to `main` (`chore: release 1.2.3`).
3. Tag that commit and push the tag: `git tag -a v1.2.3 -m "Short summary"` and `git push origin v1.2.3`. The tag message becomes the TER upload comment.
4. Write the GitHub release notes for the tag.

The workflow stops unless the tagged commit is on `main` and the tag matches both version fields. It then runs `ddev verify` on the tagged commit (the same suite as CI) and waits for approval in the `ter` environment, which holds the `TYPO3_API_TOKEN` secret, before uploading. Packagist doesn't wait for any of this, so tag only a commit whose CI is green. Pushed `v*` tags can't be moved or deleted. To check the TER artefact without publishing, run the workflow by hand on an existing tag and leave "publish" unticked; it uploads the zip as a workflow artifact.

## Need Help?

Open an issue or start a discussion on GitHub. We're here to help!