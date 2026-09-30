# TransMate Changelog

## 1.3.0 - 2026-09-30
### Added
- Added the `translatorConfig.openai.timeout` setting, for how many seconds to wait for a response from OpenAI (defaults to `60`).
- Added retries to the translate queue job, which is now attempted up to 3 times, with a TTR of 300 seconds per attempt.

### Changed
- Changed the `openai-php/client` requirement to `^0.21`.
- Changed the default OpenAI model from `gpt-4` to `gpt-6.1-sol`.
- Changed the OpenAI translator to no longer send a temperature, since current OpenAI reasoning models don't accept one. The `translatorConfig.openai.temperature` setting is now deprecated and ignored.

### Fixed
- Fixed nested entries in CKEditor fields getting a literal `$nbsp;` in their placeholder tag when translated.

## 1.2.0 - 2026-08-07
### Added
- Added the `Translate::translateText()` service method, for translating a single string of text between two sites' languages.
- Added the `Translate::translateTexts()` service method and a `TranslatorInterface::translateMany()` contract, for translating multiple strings that share a source/target language pair in a single batched request (DeepL translates them in one API call; other translators fall back to a per-string loop).

## 1.1.1 - 2026-07-03
### Fixed
- Fixed a security issue where any control panel user could translate elements into any site, regardless of their permissions.
- Fixed the translation controller actions being accessible via GET requests.

## 1.1.0 - 2026-06-23
### Added
- Adds per-site translator support: set `translator` to a map of site handles to translators.

### Changed
- `translator` now accepts a string (unchanged) or an array.

## 1.0.0 - 2026-01-27
### Added
- Initial stable (?) release
