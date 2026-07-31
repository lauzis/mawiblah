# wp-logs

Shared file-based logging for the WordPress plugins in this account (mawiblah,
splecheh, rest-in-sync, and any that follow). It replaces three near-identical
`Logs` classes that had already drifted apart — most visibly `getLogCount()`,
which counted log *entries* in two of them and log *files* in the third.

## Install

Not on Packagist. Consume it from GitHub:

```json
{
    "repositories": [
        { "type": "vcs", "url": "https://github.com/lauzis/wp-logs" }
    ],
    "require": {
        "lauzis/wp-logs": "^1.0"
    }
}
```

While developing the package alongside a plugin, point at the working copy
instead — `symlink: false` matters, so a release zip contains real files:

```json
{
    "repositories": [
        { "type": "path", "url": "../wp-logs", "options": { "symlink": false } }
    ]
}
```

The consuming plugin must load `vendor/autoload.php` early — before anything
that might log.

## Usage

Each plugin keeps its own `Logs` class as a facade, so existing call sites stay
put and plugin-specific quirks (option names, legacy settings values) stay in
the plugin where they belong:

```php
private static function logger() {
    if ( ! class_exists( 'WpLogs_Registry' ) ) {
        return null; // vendor/ missing — degrade to a no-op, never fatal
    }

    return WpLogs_Registry::logger(
        'my-plugin',
        array(
            'dir'     => MY_PLUGIN_LOG_PATH,
            'enabled' => array( __CLASS__, 'enabled' ),
        )
    );
}
```

`enabled` takes a callable so a settings change applies immediately rather than
being captured when the logger is first built.

### API

| Method | Behaviour |
| --- | --- |
| `add($action, $message, $context, $channel)` | Appends a line when enabled. Returns `false` if disabled or the write failed. |
| `error($action, $message, $context)` | Always writes to PHP's `error_log`; also to the plugin's file when enabled. |
| `count($channel)` | Number of **entries**, not files. |
| `files($channel)` | `['file', 'name', 'date', 'count']` per daily file, newest first. |
| `read($channel, $limit)` | Entries, newest first. |
| `clear($channel)` | Deletes one channel's files; pass `'*'` for all of them. |
| `dir()` | Absolute log directory path. |

Lines are `[2026-07-31 09:14:02] [action] message | {"json":"context"}`. An
empty `$action` omits that segment, for audit-style streams that carry only a
message.

### Channels

A channel is a separate log stream in the same directory, written as
`{channel}-YYYY-MM-DD.log`. The default channel is the plugin slug, which
reproduces the previous per-plugin filenames exactly, so existing log files
stay readable. splecheh's auto-apply audit log is the motivating case.

Channel names are reduced to `[a-z0-9_-]`, so a caller-supplied channel cannot
escape the log directory.

## Storage

Logs belong under `uploads/`, never inside the plugin directory — WordPress
deletes and re-extracts that folder on every update, taking the history with
it. The directory gets an `index.php` and a deny-all `.htaccess` on creation,
since `uploads/` is web-served.

## Version gating

Every plugin bundles its own copy in `vendor/`. Without arbitration PHP would
use whichever copy autoloaded first, so a plugin shipping a newer version could
silently run an older one and fatal on a method that version lacks.

`bootstrap.php` therefore registers only a version and a path.
`WpLogs_Registry` — global, `class_exists`-guarded, defined once by whichever
copy loads first — collects every registration and loads the highest version.

Two consequences:

- `WpLogs_Registry`'s own API can essentially never change, because an *old*
  copy may be the one that defines it. Keep it minimal; new behaviour goes in
  `Logger`.
- The version registered in `bootstrap.php` must be bumped in step with the
  Git tag — the registry arbitrates on the former, Composer resolves on the
  latter, and they must agree.

## Tests

```
composer test
```

Dependency-free by design: the package requires nothing, and pulling in PHPUnit
would push that resolution onto every consuming plugin. The suite stubs the few
WordPress functions the library touches.
