# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [0.2.0] - 2026-03-28

### Added
- `Process` interface with `terminate()` and `await()` methods
- `debounce(callable, float): callable` helper function
- `--debounce` CLI option (default: `0.1s`)

### Changed
- **BC break:** `ProcessWithTTY` moved to `Process\Tty`, now implements the `Process` interface
- **BC break:** `ChangeDetector` contract changed from a pull-based `createDetector()` to a subscriber model: `onChanged(Target, callable): string` and `cancel(string): void`
- **BC break:** `ChangeDetector\FileMtime` renamed to `ChangeDetector\MtimePolling`
- **BC break:** `Watcher::watch()` parameters changed: `($command, $target)` to `($target, $start, $debounce, $cancellation)`
- **BC break:** `--extension` CLI option renamed to `--ext`
