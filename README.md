# Thesis Hot Reload

Watches files for changes and automatically restarts a command.

Useful for development servers, workers, or any long-running PHP process.

Supports debouncing to avoid redundant restarts when multiple files change at once.

## Installation

```bash
composer require thesis/hot-reload --dev
```

Requires PHP 8.4+.

## Usage

```bash
vendor/bin/hot-reload [options] [--] <cmd>
```

### Options

| Option       | Description                                                     | Default |
|--------------|-----------------------------------------------------------------|---------|
| `--path`     | Paths to watch (repeatable)                                     | `[src]` |
| `--ext`      | File extensions to watch (repeatable; if none, all are watched) | `[]`    |
| `--exclude`  | Patterns to exclude (repeatable, e.g. `*.generated.php`)        | `[]`    |
| `--debounce` | Delay in seconds before restarting after a change               | `0.1`   |

### Examples

```bash
# Watch src/, restart on any file change
vendor/bin/hot-reload -- php server.php

# Watch multiple paths, only .php files
vendor/bin/hot-reload --path=src --path=config --ext=php -- php server.php

# Exclude generated files
vendor/bin/hot-reload --path=src --ext=php --exclude='*.generated.php' -- php server.php

# Increase debounce delay (useful when many files change at once)
vendor/bin/hot-reload --debounce=0.5 -- php server.php
```

## PHP API

You can use the watcher directly in PHP instead of the CLI binary:

```php
use Thesis\HotReload\Process\Tty;
use Thesis\HotReload\Target;
use Thesis\HotReload\Watcher;

$exitCode = new Watcher()->watch(
    target: new Target(
        paths: ['src'],
        extensions: ['php'],
        excludes: ['*.generated.php'],
    ),
    start: static fn() => Tty::start(['php', 'server.php']),
    debounce: 0.1,
);

exit($exitCode);
```

`Tty::start()` accepts either a string command or an array of arguments (which will be shell-escaped).

To use a custom process, implement the `Thesis\HotReload\Process` interface.

## Roadmap

- [ ] `inotify`-based change detector for Linux (event-driven, no polling)

## License

MIT
