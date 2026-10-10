## Inkmeter

Adds a panel and shows your current writing streak.

Built on [merkushin/wpal](https://github.com/merkushin/wpal).

### Development

Requirements: PHP 7.4+, Composer 2, Node.js 20.9+, WP-CLI (for translations).

```bash
make install   # composer install && npm ci
make build-js  # build assets into assets/dist
make test      # run PHPUnit
npm test       # run the JavaScript tests (Node 22+)
make smoke     # smoke test the release build from make dist
make lint      # WordPress Coding Standards and PHP 7.4+ compatibility (make lint-fix to auto-fix)
```

CI (`.github/workflows/ci.yml`) runs the linter, checks the translatable strings and that versions match, runs the tests on PHP 7.4–8.4, builds the plugin (uploaded as the `inkmeter` artifact) and runs [Plugin Check](https://wordpress.org/plugins/plugin-check/) on the build.

In a development checkout the plugin loads `vendor/autoload.php`, so it can be symlinked into a local WordPress install as-is.

### Inkmeter Pro

The optional Pro features (Settings → Inkmeter) talk to the service at streakfire.org, whose server lives in [creogen/streakfire](https://github.com/creogen/streakfire). To develop against a local copy of it, add to `wp-config.php`:

```php
define( 'INKMETER_API_URL', 'http://localhost:8080' );  // Called by WordPress itself (from Docker: http://host.docker.internal:8080).
define( 'INKMETER_SITE_URL', 'http://localhost:8080' ); // Opened in the browser for connecting.
```

Without an Emailit key, the local server prints the connect email, with its confirmation link, to its log.

### Translations

All user-facing text lives in `src/views/` and uses the `inkmeter` text domain. The plugin ships no translation files: WordPress.org asks plugins to translate through [translate.wordpress.org](https://translate.wordpress.org/projects/wp-plugins/inkmeter/), and WordPress downloads and loads those language packs automatically.

The repository has no translation template either, since translate.wordpress.org builds its own from the released code. To get one for a manual translation, generate `build/inkmeter.pot` (needs [WP-CLI](https://wp-cli.org/)):

```bash
make i18n
```

`make i18n-check`, which CI runs, fails on WP-CLI warnings about the strings, such as a string with placeholders but no `translators:` comment.

### Building a release

```bash
make dist
```

This creates `inkmeter.zip`. The build happens in `build/inkmeter`, so the working copy is left untouched:

1. Plugin files and built assets are copied to `build/inkmeter`.
2. Runtime dependencies are installed there. `bin/prune-wpal.php` finds the wpal services used in `src/` (`use Merkushin\Wpal\Service\…` and `ServiceFactory::create_…()`) and tells wp-scoper to copy only those plus `ServiceFactory`; wpal's PHP 8.4+ Api layer is always left out. The build fails if `src/` references a service wpal doesn't have.
3. Dependencies are prefixed with [wp-scoper](https://github.com/veronalabs/wp-scoper) into `vendor-prefixed/` (namespace `Merkushin\Inkmeter\Vendor`), and the `use` statements in `src/` are rewritten to match.
4. `vendor/` and the Composer files are removed and the directory is zipped.

`make smoke` then loads the build with stubbed WordPress functions and runs every code path, so a wpal class missing from the build fails CI rather than a site. Using a new wpal service needs no configuration: reference it in `src/` and the next build includes it.

`vendor-prefixed/` is never committed. The wp-scoper Composer plugin is disabled in `composer.json` (`allow-plugins`) so it doesn't rewrite the sources on `composer install`; it only runs from `make dist`.

### Releasing

1. Bump the version in `inkmeter.php` (header), `readme.txt` (`Stable tag`), `Plugin::VERSION` and `package.json`, and add a changelog entry to `readme.txt`. `make version-check` confirms they match.
2. Push a tag with that version, e.g. `v1.0.1`. The release workflow builds the zip, deploys it to WordPress.org SVN with the listing assets from `.wordpress-org/`, and creates a GitHub release with the zip attached.

The workflow needs the `SVN_USERNAME` and `SVN_PASSWORD` repository secrets (your WordPress.org username and SVN password). The very first version is submitted by hand at [wordpress.org/plugins/developers/add](https://wordpress.org/plugins/developers/add/); tags only deploy after the plugin is approved.

