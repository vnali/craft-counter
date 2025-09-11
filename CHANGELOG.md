# Release Notes for Counter plugin for Craft 5

## 1.1.0 - 2025-09-11
- `widgetTitleTruncateLength` and `removeDomainFromResult` can now be configured via the plugin settings.
- Removed the advanced setting “Prevent HTTP requests for the counter” from the plugin settings; it can now be configured via `disableCountController` in the config file. [(docs)](https://plugins.vnali.dev/counter/settings#config-file)

## 1.1.0-beta.3 - 2025-09-10
- Added a `widgetTitleTruncateLength` setting to customize how long page titles are truncated in widgets. [(docs)](https://plugins.vnali.dev/counter/settings#config-file)
- Changed page title truncation to only apply to widgets.
- Fixed a bug that caused widget URLs to be altered (HTML-escaped) unnecessarily.

## 1.1.0-beta.2 - 2025-09-05
- Added a `removeDomainFromResult` setting to remove the domain name from URLs in the statistics results.
- Changed the 'Trending pages', 'Declining pages' and 'Not visited pages' widgets to filter pages related to elements and return the element's title. [(docs)](https://plugins.vnali.dev/counter/widgets#trending-pages)
- Changed the 'trendingPages' statistics to filter pages related to elements and return element's title. [(docs)](https://plugins.vnali.dev/counter/statistics/trending-pages)
- Changed the 'trendingPages' statistics to return the url, elementId, elementType, and siteId. [(docs)](https://plugins.vnali.dev/counter/statistics/trending-pages)
- Changed the 'Ignore new pages' label in the Trending page widget to 'Exclude pages without previous visits' for better clarity.
- Fixed a bug where the 'Remove the URL fragment' setting only worked if either 'Remove all query parameters' or 'Remove the URL fragment' was enabled.

## 1.1.0-beta.1 - 2025-08-28

> {warning} If "Compatibility with outdated browsers" is enabled in your plugin settings and JQuery is already loaded on the frontend, you should set the autoImportJquery setting to false to prevent the plugin from loading jQuery again.

- Added a utility to import statistics from the Views Work plugin. [(#1)](https://github.com/vnali/craft-counter-docs/issues/1) - [(docs)](https://plugins.vnali.dev/counter/import-from-views-work)
- Added counter statistics to the entry and category index pages. [(docs)](https://plugins.vnali.dev/counter/statistics/page-visits#page-visits-in-the-control-panel)
- Added `autoImportJquery` to the plugin config to prevent loading jQuery twice. [(docs)](https://plugins.vnali.dev/counter/settings#config-file)
- Added counter statistics to the entry and category GQL queries. [(docs)](https://plugins.vnali.dev/counter/statistics/page-visits#getting-page-visits-of-entries-and-categories)
- Changed the top page widget to filter pages related to elements and return the element's title. [(docs)](https://plugins.vnali.dev/counter/widgets#top-pages)
- Changed the top page statistics to filter pages related to elements and return element's title. [(docs)](https://plugins.vnali.dev/counter/statistics/top-pages)
- Top page statistics now also return the url, elementId, elementType, and siteId. [(docs)](https://plugins.vnali.dev/counter/statistics/top-pages)
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