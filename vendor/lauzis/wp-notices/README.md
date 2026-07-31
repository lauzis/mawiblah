# wp-notices

Shared admin notices and toast messages for the WordPress plugins in this
account. Companion to [wp-logs](https://github.com/lauzis/wp-logs).

Two components, because the plugins were solving two different problems:

- **Notices** — server-rendered, dismissible messages in the admin notice area.
  They describe a *standing condition* ("the spell-check library is missing").
  splecheh and mawiblah each had their own implementation, with different
  dismissal strategies.
- **Toasts** — transient floating messages raised from JavaScript, auto-
  dismissed, never persisted. They report the *outcome of an action the user
  just took* ("Pushed 3 posts"). rest-in-sync had this one.

They are not variants of each other, so the package ships both rather than
trying to unify them.

## Install

```json
{
    "repositories": [
        { "type": "vcs", "url": "https://github.com/lauzis/wp-notices" }
    ],
    "require": {
        "lauzis/wp-notices": "^1.0"
    }
}
```

## Notices

```php
$notices = WpNotices_Registry::notices(
    'my-plugin',
    array(
        'store'   => 'option',            // or 'user'
        'version' => MY_PLUGIN_VERSION,   // for VERSION-mode notices
        'screen'  => array( __CLASS__, 'is_plugin_screen' ),
    )
)->boot();

$notices->add(
    new \Lauzis\WpNotices\Notice(
        'missing-lib',
        __( 'The spell-check library is missing.', 'my-plugin' ),
        'error',
        \Lauzis\WpNotices\Notice::ONCE
    )
);
```

`boot()` registers the `admin_notices`, `admin_enqueue_scripts` and
`wp_ajax_*` hooks. It is idempotent.

### Dismissal modes

| Mode | Dismissal lasts |
| --- | --- |
| `Notice::ONCE` | Forever. |
| `Notice::VERSION` | Until the configured `version` changes — "dismiss for this version". |
| `Notice::SESSION` | Not persisted; reappears on the next page load. |

### Where dismissals are stored

`store` is `option` (site-wide, one dismissal for everyone) or `user`
(per-user, in user meta). Both write to a key named
`{slug}_dismissed_notices`.

### Screen scoping

`screen` is a callable returning whether notices should render. Omit it and the
default applies: any admin page whose `page` request parameter starts with the
plugin slug.

### Message escaping

Messages pass through `wp_kses_post()`, so inline links and emphasis work and
scripts do not. Never interpolate unescaped user input into a message.

## Toasts

```php
WpNotices_Registry::toasts( 'my-plugin', array( 'timeout' => 5000 ) )->enqueue();
```

Then from JavaScript:

```js
window.wpNoticesToast.show( 'Pushed 3 posts.', 'success' );
```

Types are `success`, `error`, `warning`, `info`. Scripts that call it should
declare a dependency on the `wp-notices-toasts` handle so the global exists
before they run.

Messages are inserted with `textContent`, not `innerHTML` — toast text
routinely comes from server responses and post titles.

## Assets

The CSS and JS ship inside the package and are enqueued from
`vendor/lauzis/wp-notices/assets/`. Their URL is derived by mapping the
filesystem path onto the plugin directory URL, which works wherever the plugin
is installed. If your layout defeats that — a symlinked `vendor/`, or the
package installed outside a plugin — pass an explicit `assets_url`.

Because assets must come from the same copy as the code, the registry resolves
them against the *winning* copy's directory, not the caller's.

## Version gating

Identical in shape to wp-logs: `bootstrap.php` registers a version and a path,
and `WpNotices_Registry` — global, `class_exists`-guarded — loads the highest
version registered across all active plugins. The registry API must stay
backwards compatible essentially forever, since an old copy may be the one that
defines it. The version in `bootstrap.php`, the one in `Assets::VERSION` and
the Git tag move together.

## Tests

```
composer test
```

Dependency-free, stubbing the WordPress functions the library touches.
