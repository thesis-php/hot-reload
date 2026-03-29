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
| `--path`     | Paths to watch (repeatable)                                     | `src`   |
| `--ext`      | File extensions to watch (repeatable; if none, all are watched) | none    |
| `--exclude`  | Patterns to exclude (repeatable, e.g. `*.generated.php`)        | none    |
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

You can use `hotReload()` directly in PHP instead of the CLI binary:

```php
use Amp\Cancellation;
use Amp\Http\Server\SocketHttpServer;
use function Amp\trapSignal;
use function Thesis\HotReload\hotReload;

hotReload(
    files: __DIR__ . '/path/to/src',
    process: static function (Cancellation $cancellation): void {
        $server = SocketHttpServer::createForDirectAccess(/** ... */);

        $cancellation->subscribe($server->stop(...));

        $server->expose(/** ... */);

        $server->start(/** ... */);

        trapSignal([SIGINT, SIGTERM], cancellation: $cancellation);

        $server->stop();
    },
    debounce: 0.2,
);
```

`$cancellation` is cancelled when files change or when `hotReload()` itself is cancelled — subscribe to it to stop the process gracefully.

## Roadmap

- [ ] [inotify](https://www.php.net/manual/en/book.inotify.php)-based change detector for Linux (event-driven, no polling)

## License

MIT
