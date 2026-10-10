PLUGIN    := inkmeter
BUILD_DIR := build/$(PLUGIN)
ZIP       := $(PLUGIN).zip
WP        ?= wp

POT_ARGS := --slug=$(PLUGIN) --domain=$(PLUGIN) --include=$(PLUGIN).php,src,assets/javascript --exclude=build,vendor,node_modules,tests \
	--headers='{"Report-Msgid-Bugs-To":"https://github.com/merkushin/inkmeter/issues"}'

# Files and directories that ship in the plugin zip.
DIST_FILES := $(PLUGIN).php uninstall.php readme.txt src LICENSE

.PHONY: all install build-js test smoke lint lint-fix version-check i18n i18n-check dist clean

all: dist

install:
	composer install
	npm ci

build-js:
	npm run build

test:
	vendor/bin/phpunit

# Loads the release build from `make dist` with stubbed WordPress functions and runs every code path.
smoke:
	php tests/smoke/dist.php $(BUILD_DIR)

# WordPress Coding Standards and PHP 7.4+ compatibility, see phpcs.xml.dist.
lint:
	vendor/bin/phpcs

lint-fix:
	vendor/bin/phpcbf

# The version must match in the plugin header, readme.txt (Stable tag), Plugin::VERSION
# and package.json. With TAG=v1.2.3 (or 1.2.3) it must also match the release tag.
version-check:
	@version=$$(sed -n 's/^ \* Version: *//p' $(PLUGIN).php); \
	for other in \
		"readme.txt:$$(sed -n 's/^Stable tag: *//p' readme.txt)" \
		"src/Plugin.php:$$(sed -n "s/.*const VERSION = '\(.*\)';/\1/p" src/Plugin.php)" \
		"package.json:$$(php -r 'echo json_decode(file_get_contents("package.json"))->version;')"; do \
		if [ "$${other#*:}" != "$$version" ]; then echo "$${other%%:*} has version $${other#*:}, $(PLUGIN).php has $$version"; exit 1; fi; \
	done; \
	if [ -n "$(TAG)" ] && [ "$(patsubst v%,%,$(TAG))" != "$$version" ]; then echo "Tag $(TAG) does not match version $$version"; exit 1; fi; \
	echo "Version $$version"

# Regenerates languages/$(PLUGIN).pot from the sources and merges it into every .po file.
# Run after changing translatable strings, then translate the new entries.
i18n:
	$(WP) i18n make-pot . languages/$(PLUGIN).pot $(POT_ARGS)
	$(WP) i18n update-po languages/$(PLUGIN).pot languages

# Fails when languages/$(PLUGIN).pot is out of date with the sources (ignores the creation date).
i18n-check:
	mkdir -p build
	$(WP) i18n make-pot . build/$(PLUGIN).pot $(POT_ARGS)
	grep -v '^"POT-Creation-Date:' languages/$(PLUGIN).pot > build/expected.pot
	grep -v '^"POT-Creation-Date:' build/$(PLUGIN).pot > build/actual.pot
	diff -u build/expected.pot build/actual.pot || (echo "languages/$(PLUGIN).pot is out of date, run 'make i18n'." && exit 1)

# Builds a release zip in a separate directory, so the working copy is never modified:
# copies the plugin files, installs runtime dependencies, keeps only
# the wpal services the plugin uses, prefixes them with wp-scoper into vendor-prefixed/ and
# drops vendor/ and the Composer files.
dist: clean install build-js
	mkdir -p $(BUILD_DIR)/assets
	cp -R $(DIST_FILES) composer.json composer.lock $(BUILD_DIR)/
	cp -R assets/dist $(BUILD_DIR)/assets/
	composer install --working-dir=$(BUILD_DIR) --no-dev --no-plugins --no-scripts --no-autoloader --no-interaction --quiet
	php bin/prune-wpal.php $(BUILD_DIR)
	vendor/bin/wp-scoper $(BUILD_DIR)
	rm -rf $(BUILD_DIR)/vendor $(BUILD_DIR)/composer.json $(BUILD_DIR)/composer.lock
	cd build && zip -rq ../$(ZIP) $(PLUGIN)

clean:
	rm -rf build assets/dist $(ZIP)
