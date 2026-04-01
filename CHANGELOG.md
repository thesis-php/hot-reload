# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [0.4.0] - 2026-04-01

### Added
- `--forward-signal` CLI option — forward signals to the process (e.g. `SIGUSR1` or `10`)
- `TransparentProcess::start()` — runs a command inheriting stdin/stdout/stderr and forwarding signals
- **BC break:** `$onReload` parameter in `hotReload()` — optional callback invoked before each reload

### Fixed
- `hotReload()` now correctly terminates the running task when files change; previously the task could outlive the restart
- `hotReload()` no longer outputs anything; use `$onReload` to handle reload notifications

### Changed
- **BC break:** `$process` parameter of `hotReload()` renamed to `$task`
- **BC break:** `bin/hot-reload` no longer forwards any signals by default; use `--forward-signal` to opt in
- **BC break:** Default `--term-timeout` changed from `10s` to `3s`
- Require `thesis/exceptionally` `^0.3`
- Require `symfony/console` `^7.0 || ^8.0`

## [0.3.1] - 2026-03-31

### Added
- `--term-timeout` CLI option (default: `10s`) — time to wait for the process to exit after `SIGTERM` before sending `SIGKILL`

### Fixed
- `proc_close()` no longer blocks when the process is slow to respond to `SIGTERM`; it is now called only after `SIGCHLD` confirms the process has exited
- `SIGKILL` is sent automatically after `$terminationTimeout` seconds if the process does not exit on `SIGTERM`

## [0.3.0] - 2026-03-30

### Added
- `hotReload<T>(callable(Cancellation): T): T` function as the new top-level PHP API

### Changed
- **BC break:** `Target` renamed to `Files`
- **BC break:** `ChangeDetector\MtimePolling` renamed to `ChangeDetector\FileMtimePoller`
- **BC break:** `ChangeDetector::onChanged(Target $target)` parameter changed to `Files $files`

### Removed
- **BC break:** `debounce` function (now internal)
- **BC break:** `Watcher` class; use `hotReload()` instead
- **BC break:** `Process` interface
- **BC break:** `Process\Tty` class (replaced by internal `TtyProcess`)
- **BC break:** `ChangeDetector\Finder` class (logic merged into `Files`)

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
