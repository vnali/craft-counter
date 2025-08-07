# Release Notes for Counter plugin for Craft 5

## Unreleased

- Added a utility to import statistics from the Views Work plugin. [(#1)](https://github.com/vnali/craft-counter-docs/issues/1)
- Added counter statistics to the entry and category index pages.
- Added the possibility for top pages to return the element title instead of the page URL for pages that return an element.
- Changed the top page statistics to filter pages related to the elements.
- Fixed a bug where widgets are not loaded when the plugin settings are not configured.
- Fixed a bug where page URLs are not truncated when widgets are set to load after the page load.
- Minimally change the label of column in the page visits widget from value to Visits.

## 1.0.0-beta.2 - 2025-01-11

- The counter plugin can be used when Craft is in headless mode. [(#2)](https://github.com/vnali/craft-counter/discussions/2)
- The default value for the `visitsInterval` setting has been changed from 60 seconds to 1 second. [(#2)](https://github.com/vnali/craft-counter/discussions/2)
- Fixed a bug where passing invalid data to the `visitsInterval` and `onlineThreshold` plugin settings caused errors.

## 1.0.0-beta.1 - 2024-11-28

- Added Craft 4 compatibility.

## 1.0.0-alpha.5 - 2024-11-23

> {tip} Please read [this announcement](https://github.com/vnali/craft-counter/discussions/1) before update.

- Counter widgets can now load from the cache.
- Counter widgets can now load after the page has loaded.
- The date ranges for the next visited pages widget are now limited to smaller ranges.
- Improved the performance of some queries by adding new index.
- Fixed a bug that may have caused concurrent visits to be logged incorrectly.

## 1.0.0-alpha.4 - 2024-10-27

- Fixed a bug where the visitor data on the maximum online users widget was calculated incorrectly.

## 1.0.0-alpha.3 - 2024-10-26

> {tip} If you are upgrading from a prior version, you should set the plugin site settings again after the upgrade.

- Fixed a bug where site settings in project settings did not apply correctly to other environments.
- Fixed a SQL error on the next visited pages widget.

## 1.0.0-alpha.2 - 2024-10-25

- Fixed a bug on plugin installation where database is MySQL.

## 1.0.0-alpha.1 - 2024-10-25

- Initial Release