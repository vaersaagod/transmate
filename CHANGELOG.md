# TransMate Changelog

## Unreleased
### Added
- Added the `Translate::translateText()` service method, for translating a single string of text between two sites' languages.

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
