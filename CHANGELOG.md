# Release Notes for Counter plugin for Craft 5

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