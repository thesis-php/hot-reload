# Thesis Hot Reload

Watches files for changes and automatically restarts a command.
Useful for development servers, workers, or any long-running PHP process.

## Installation

```bash
composer require thesis/hot-reload
```

Requires PHP 8.4+.

## Usage

```bash
vendor/bin/hot-reload [options] [--] <cmd>
```

### Options

| Option        | Description                                                     | Default |
|---------------|-----------------------------------------------------------------|---------|
| `--path`      | Paths to watch (repeatable)                                     | `src`   |
| `--extension` | File extensions to watch (repeatable; if none, all are watched) | all     |
| `--exclude`   | Patterns to exclude (repeatable, e.g. `*.generated.php`)        | —       |

### Examples

```bash
# Watch src/, restart on any file change
vendor/bin/hot-reload -- php server.php

# Watch multiple paths, only .php files
vendor/bin/hot-reload --path=src --path=config --extension=php -- php server.php

# Exclude generated files
vendor/bin/hot-reload --path=src --extension=php --exclude='*.generated.php' -- php server.php
```

## License

MIT
