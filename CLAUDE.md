# BUG FIXES — MINIMAL DIFFS (read before editing any existing function)

**A bugfix changes ONLY the lines that fix the bug.** No restructuring, no renaming, no restyling on the way past, no "refactor" commit inside a bugfix PR.

**Read the function's history first:** `git log --oneline -L <start>,<end>:<file>`. The hot code gets edited over and over, and the fixes it holds are not visible in the code itself. The drag-and-drop block in `assets/public/js/script.js` carries 17 commits, `Controller\Common\API::register_endpoints()` 14, `openModal()` in `script.js` 13, `Shortcode::callback_roadmap()` 11, `Model\Roadmap::get_roadmap()` 10 (plus 5 more before it was extracted, see below), `API\Task::get()` 8. Examples of fixes you can't see:

- The per-item permission checks `Auth::can_move_task()`, `can_order_tasks()` and `can_order_stages()` came in `eb1e081` *misc* (no PR, no issue, no body). Before that, the routes only checked `edit_pages`, with no check on the item or status itself.
- The guards in `Common\Init::order_terms()` (only full `WP_Term` objects are sorted, unsorted statuses go last) came in `2e8dc16` *reinit*.
- `board.php` puts `data-nr-per-column` after `hidden` so the markup the tests match keeps its shape, and `NiroRoadmapPaging.reveal()` keeps a dropped card visible (`94ea24f`, PR #57).
- `Getting_Started::show_help_notice()` marks the checklist done for sites that were already set up (`eb050b5`, no PR).

Treat existing code as accumulated fixes, not as arbitrary shape; the code IS the spec. Skipping this step is how a rewrite silently reverts an earlier fix and ships a new bug inside a bugfix.

**A list of hashes is not the history. For EVERY commit `-L` returns, read three things: the commit, its PR, and its issue.** The one-line subject is a label, not a reason. The reason lives in the commit body, the PR description and the issue that prompted it, and that is where the constraint you are about to break is written down.

If a 10-line function was built by 6 commits, that is **6 commit messages + 6 PR bodies + 6 issues to read before you touch it**: all six, not the newest one, not "the relevant-looking ones". A rule you did not read is a rule you will delete.

```bash
git log --oneline -L '/public static function get_roadmap/,+163:app/Model/Roadmap.php'   # the hashes
git show <sha>                                                                            # full body + diff, per hash
gh api repos/codexpertio/niroroadmap/commits/<sha>/pulls --jq '.[].number'                # commit → PR
gh pr view <n> --json title,body,comments                                                 # why it was written that way
gh issue view <nn>                                                                        # the report + repro it had to satisfy
```

Where the PR and issue are in this repo:

- **PRs #46–#54 (the 1.0 features) were rebase-merged.** There is no merge commit, and the `Closes #NN` is in the **PR body**, not the commit body. Use the API to go from commit to PR. A rebase can change the SHA: PR #51's `19ba64c` is `54cf40c` on master, so the API is the reliable route.
- **PR #57 and later** are merge commits (`Merge pull request #NN from …`) and the fix commit carries `Closes #NN` in its body. Take the number from there; fall back to the API only when it is missing.
- **PR #47** (`bed152e`, issue #18) was merged into `feature/19-settings-page`, not master. Its commit still reached master, and the API still maps it to #47.
- Before `0fc93ea` *rename* (2026-09-10) the plugin was EasyRoadmap. The only PRs then were #1, #2, #11, #12, #16 and #17 (merge commits).

**If a commit has no PR and no issue, say so** in your list. That is a real finding: the constraint is undocumented and the commit body is all there is. Here that is 67 of the 73 commits before the rename, with no body either (`misc`, `cs`, `reinit`, `re-re-init`, `More fixes`), so the diff read line by line is the only record. After 1.0 it is every commit outside PRs #54 and #57, including `1a2cda5` (onboarding) and `eb050b5` (a fix inside a changelog commit).

**If the function was moved or extracted, follow it.** `git log -L` follows a file rename but does NOT track content across files, so it reports almost no history on exactly the code that has the most.

- `Roadmap::get_roadmap()` began inside `Shortcode::callback_roadmap()`, moved to `Controller/Common/Roadmap.php` in `b1e66a8`, then to `Model/Roadmap.php` in `3d53269`. `-L` follows the rename back to `b1e66a8` and stops; the 5 commits before it are in `Shortcode.php`:
  ```bash
  git log --oneline -L '/public function callback_roadmap/,+45:app/Controller/Public/Shortcode.php' b1e66a8^
  ```
- `c5eb080` split `views/shortcodes/roadmap.php` into `board.php`, `list.php` and `timeline.php`. `-L` on `board.php` shows 3 commits; the board markup's earlier history is in `roadmap.php`:
  ```bash
  git log --oneline -L '/nr-kanban-columns/,+60:views/shortcodes/roadmap.php' c5eb080^
  ```

- PHP 7.4 support renamed `app/Trait/` → `app/Traits/` and `app/Controller/Public/` → `app/Controller/Front/` (namespaces with them). `-L` follows those renames; `git log -- <old path>` does not.

`git log --oneline --diff-filter=ADR -M -- <file>` lists where a file was added, deleted or renamed.

**List them in the PR body:** which prior fixes touch these lines, and which the diff preserves. If the diff moves one, say why. No list means nobody looked.

**Size gate:** more than ~20 changed lines inside an existing function in a bugfix → stop, justify, or split. A big bugfix diff is churn until proven otherwise. Reference points: `eb050b5` fixed the setup reminder for sites already set up with `+6` lines in `show_help_notice()`; the review fixes for PR #57 (`94ea24f`) changed one line of `board.php` and added a separate `reveal()` rather than rewriting `refresh()`.

**Never extract a shared helper as part of a bugfix.** Several rules are hand-written in more than one place, and the copies have already drifted apart:

- "Which items are public" is checked in three places: `Roadmap::get_public_item()` (REST), `Submission::similar()` (`has_password => false`) and the board query in `get_roadmap()`. The board query is missing the password check (#62).
- "Every item, whatever its status" is written as `post_status => 'any'` in `uninstall.php` and twice in `Sample_Data`. All three miss trashed items (#60).
- What a setting hides (vote counts, tags, target) is enforced separately in `Toolbar::config()`, `Views::config()`, `Task::can_see_count()`, `Fields::public_facts()` and each template.

Say so when you notice it, but consolidation is its own scheduled PR with behaviour locked by tests first, never a side effect of fixing one instance.

---

# CLAUDE.md — NiroRoadmap

Public product roadmap for WordPress by NiroSuite (Codexpert). Items are a custom post type shown as a Kanban
board, a list or a timeline through the `[niroroadmap]` shortcode or the `niroroadmap/roadmap` block. Visitors
vote, comment and suggest ideas; editors drag cards on the public board. Published on WordPress.org as `niroroadmap`.

## Key facts

| Item | Value |
|---|---|
| Version | 1.2.0 — plugin header `Version`, `NIROROADMAP_VERSION` and readme.txt `Stable tag` must agree (the release workflow checks header and Stable tag against the tag; nothing checks the constant) |
| PHP namespace | `NiroRoadmap\` → `app/` (PSR-4, Proper_Case files: `app/Model/Status_Log.php` = `NiroRoadmap\Model\Status_Log`) |
| Autoload files | `app/Helper/functions.php`, `app/Config/autoload.php` (Composer `files`) |
| Requires | PHP 7.4 (runs on 7.4 through 8.5), WP 6.0, tested up to 7.1 |
| Text domain | `niroroadmap` (no `load_plugin_textdomain()` call) |
| REST namespace | `niroroadmap/v1` (`Traits\Rest::$namespace`) |
| Prefixes | `niroroadmap_` options/hooks/meta, `NIROROADMAP_` constants, `niroroadmap-` script handles, `nr-` CSS classes and DOM ids, `data-nr-*` JS hooks |
| Runtime deps | none (Composer is autoload only) |
| License | GPLv2 or later |

`vendor/` and `build/` are gitignored. A fresh checkout fatals on `require_once 'vendor/autoload.php'` until `composer install`; without `build/` the block is simply not registered (`Common\Blocks` checks for `build/blocks-manifest.php`).

## Commands

```bash
composer install            # PHP deps (dev: phpunit 9.6, brain/monkey, phpcs + WPCS + PHPCompatibility); platform pinned to PHP 7.4
composer test               # php -d auto_prepend_file=tests/bootstrap.php vendor/bin/phpunit (see Tests)
composer makepot            # wp i18n make-pot → languages/niroroadmap.pot (needs WP-CLI)
composer release            # build blocks, makepot, --no-dev install, zip → release/niroroadmap.zip

npm install
npm run start:blocks        # wp-scripts watch: app/Blocks → build/
npm run build:blocks        # wp-scripts build --blocks-manifest → build/ (only the block is compiled)
```

Only `app/Blocks/roadmap/` is compiled. Everything in `assets/` is plain JS/CSS served as-is — edit and reload,
no build. There is no `phpcs.xml` and no lint script, although WPCS is a dev dependency.

## Directory layout

```
niroroadmap.php           Header, constants, activation/deactivation/plugins_loaded wiring, requires vendor/autoload.php
uninstall.php             Option cleanup; full data removal only with the delete_on_uninstall setting
app/
  Bootstrap/              Installer (activation), Activator (every load), Initializer (controllers), Uninstaller (deactivation!)
    Activator/            Post_Type, Taxonomy (registered on init)
  Controller/
    Admin/                Asset, Getting_Started, Init (status color field), Item_Fields, Settings, Submissions
    Common/               API (REST routes), Blocks, Comments (auto-close), Fields (register meta), Init (assets, term order, vote cleanup), Status_History
    Front/                Front (assets, body class, JS strings), Shortcode
  API/                    REST handlers: Task, Comment, Submission, Stage, Option
  Model/                  Roadmap (board data + render), Vote, Comment, Submission, Fields, Status_Log, Toolbar, Views
  Helper/                 Settings (schema), Voter, Rate_Limit, Sample_Data, Utility, functions.php
  Traits/                 Hook, Asset, Rest, Auth, Menu, Cleaner, Cache, Limiter
  Blocks/roadmap/         block.json, index.js, edit.js, render.php, editor.scss (source of build/roadmap/)
  Config/autoload.php     Requires every other app/Config/*.php (there are none)
views/shortcodes/         roadmap.php (wrapper + popup), board.php, list.php, timeline.php, toolbar.php, switcher.php, suggest.php
assets/
  common/                 init.js, init.css, img/loader.gif — loaded on every front and admin page
  public/js/              script.js (popup, votes, comments, suggest, drag, Load more), toolbar.js, views.js
  public/css/             style.css, toolbar.css, views.css
  admin/                  css/style.css + js/sorter.js (status screen), css/getting-started.css; js/init.js, js/settings.js, css/settings.css are not loaded
tests/                    bootstrap.php + PHP/*Test.php (integration, real WP database)
languages/                niroroadmap.pot (stale header, regenerated by composer release), cx-plugin.pot (scaffold leftover)
svn-assets/               WordPress.org banners, icons, screenshots, blueprints/blueprint.json (Playground demo)
.github/workflows/        wp-svn-release.yml (tag → WordPress.org SVN)
```

## Load order

1. Activation → `Installer::install()` (see Lifecycle).
2. `plugins_loaded` → `Activator::activate()`: CPT and taxonomies on `init`, `Installer::seed_statuses()` on `init` 20, `Installer::maybe_upgrade()` (creates the two tables when their schema option differs), `update_option( 'niroroadmap_activated', true )`.
3. `plugins_loaded` → `Initializer::initialize()` instantiates every class in `Controller/Admin/` when `is_admin()`, `Controller/Front/` otherwise, and `Controller/Common/` always, by globbing the folder. **A new controller file is loaded automatically**; its constructor registers hooks through `Traits\Hook` (`action()`, `filter()`, `shortcode()`).

Admin controllers are constructed before Common ones, so on the same hook and priority their callbacks run first.

## Data model

| Name | Kind | Notes |
|---|---|---|
| `niroroadmap_item` | post type | Labels "Items", menu "NiroRoadmap", `capability_type` page, rewrite `tasks`, archive on, `show_in_rest`, supports title, editor, author, comments, thumbnail (thumbnail = popup cover) |
| `niroroadmap_status` | taxonomy (hierarchical) | Board columns. Rewrite slug `product-cat` (EasyCommerce leftover, live URL — don't rename). Term meta `color`, `menu_order` |
| `niroroadmap_product` | taxonomy (hierarchical) | Rewrite `product-brand` |
| `niroroadmap_tag` | taxonomy (flat) | Rewrite `product-tag` |
| `{prefix}niroroadmap_votes` | table | One row per voter per item; UNIQUE `(item_id, voter_hash)` and `(item_id, fingerprint_hash)`; schema option `niroroadmap_schema_version` |
| `{prefix}niroroadmap_status_log` | table | from/to term id + name snapshot, `user_id`, `created_at` UTC; schema option `niroroadmap_status_log_schema` |
| `upvote`, `downvote` | post meta (unprefixed) | Display counters, changed only by `Vote::bump()` in one SQL statement; existing pre-table counts are kept |
| `_niroroadmap_target`, `_target_sort`, `_release_date`, `_version`, `_effort`, `_notes`, `_link`, `_hide_votes`, `_pinned` | post meta | `Model\Fields` constants; registered with `show_in_rest => false` on purpose |
| `_niroroadmap_submitted`, `_submitter_name`, `_submitter_email` | post meta | Visitor submissions (`Model\Submission` constants) |
| `_niroroadmap_sample` | post + term meta | Example content flag (`Sample_Data::FLAG`) |
| `niroroadmap_settings` | option | All settings (see Settings) |
| `niroroadmap_db_version`, `niroroadmap_activated`, `niroroadmap_page_id` (legacy roadmap page), `niroroadmap_statuses_seeded` (`pending`/`yes`), `niroroadmap_getting_started_done` | options | |
| `niroroadmap_activation_redirect` (60 s), `niroroadmap_field_errors_{user_id}` (5 min), `niroroadmap_rl_{md5}` | transients | Redirect to Getting Started; meta box errors; rate limit windows |
| `niroroadmap_voter` | cookie | Anonymous voter token, 32 hex chars, 1 year, HttpOnly, SameSite=Lax |
| `niroroadmap_votes`, `niroroadmap_view` | localStorage | UI cache of own votes; last chosen view |

Default statuses seeded once on a fresh site: Under Review `#a855f7`, Planned `#3b82f6`, In Progress `#f59e0b`, Completed `#22c55e` (slugs `under-review`, `planned`, `in-progress`, `completed`). Deleted defaults never come back. `submissions_status` defaults to the `under-review` term; comment auto-close matches slug `completed`; the tests look these slugs up.

Status order: term meta `menu_order`, applied by the `get_terms` filter in `Common\Init::order_terms()` to every full-object query on `niroroadmap_status` (unsorted terms last, by id). Item order: `menu_order` of the post, pinned items first (stable sort).

## Settings

`Helper\Settings::fields()` is the single schema: tab, type (`checkbox|select|color|number|textarea|email`), label,
description, default (value or Closure), options (Closure), `cast` `int`, `min`/`max`. It drives defaults,
`Settings::get()` casting, `Settings::sanitize()` and the form in `Controller\Admin\Settings`.

| Tab | Keys (default) |
|---|---|
| general | `roadmap_page` (legacy option), `default_product` (0), `stage_links` (off), `show_vote_counts` (on), `show_tags` (on), `show_target` (off), `show_history` (off), `show_downvote` (on) |
| voting | `vote_who` (`everyone`/`logged_in`), `allow_vote_change` (off), `show_downvotes_to` (`everyone`/`admins`) |
| comments | `comments_enabled` (off), `comments_login` (off), `comments_order` (`oldest`/`newest`), `comments_auto_close` (off) |
| submissions | `submissions_enabled` (off), `submissions_login` (off), `submissions_identity` (`off`/`optional`/`required`, optional), `submissions_status` (under-review term), `submissions_auto_vote` (on), `submissions_notify` (on), `submissions_email` ('') |
| toolbar | `toolbar_enabled` (off), `toolbar_sort` (`manual`), `toolbar_filter_search`, `toolbar_filter_tag`, `toolbar_filter_product` (on) |
| views | `view_default` (`board`), `view_switcher` (off), `view_mobile_list` (off), `view_timeline_group` (`quarter`), `board_per_column` (10, 0–500) |
| appearance | `color_scheme` (`light`/`dark`/`auto`), `accent_color` (`#2563eb`), `card_radius` (12, 0–32), `custom_css` ('') |
| advanced | `delete_on_uninstall` (off) |

- Read with `niroroadmap_get_setting( $key )`; the `niroroadmap_setting_{$key}` filter has the last word (tests pin settings this way).
- The form posts a hidden `_tab`; unchecked checkboxes of that tab become false, everything else absent keeps its value. An invalid value is rejected with a settings error and keeps the saved value. `sanitize()` must give the same answer on its own output: WordPress runs it twice on the first save.
- `Settings::update( $key, $value )` writes one key without validation — internal use only (installer, blueprint).
- `Settings::inline_css()` turns accent, radius and scheme into CSS variables on `.nr-board,.nr-kanban-columns,.nr-modal-overlay` and appends `custom_css`.
- Reset: `admin-post.php?action=niroroadmap_reset_settings` (nonce + `manage_options`) deletes the option.

Adding a setting: schema entry in `fields()`; read it with `niroroadmap_get_setting()`; pin it in the `setUp()` of every test class whose output it changes; describe it in readme.txt (Settings, Shortcode, FAQ). A per-board override also needs the shortcode attribute in `Shortcode::callback_roadmap()`, the attribute in `block.json`, the control in `edit.js`, sanitizing in `render.php`, and resolution in `Toolbar::config()` / `Views::config()` / `Submission::enabled()`.

## Rendering a board

`[niroroadmap]` / `[roadmap]` (`shortcode_atts` tag `roadmap`) and the block's `render.php` both call
`Model\Roadmap::get_roadmap( $product, $args )`. The block has no `product` attribute (TODO in render.php); it
always uses `default_product`.

| Attribute | Values | Resolved in |
|---|---|---|
| `product` | term id | `get_roadmap()` (null → `default_product`) |
| `toolbar`, `sort`, `filters` | `yes`/`no`; `manual votes newest oldest commented`; comma list of `search tag product` or `none` | `Toolbar::config()` |
| `view`, `switcher`, `group`, `per_column` (block: `perColumn`) | `board list timeline`; `yes`/`no`; `quarter month nownext`; 0–500 | `Views::config()` |
| `submissions` | `yes`/`no` | `Submission::enabled()` |

Anything else falls back to the setting, then to a safe default. What a board may offer is decided on the server
so a setting can't be bypassed: no `votes` sort when counts are hidden, no `commented` sort without comments, no
tag filter when tags are hidden, no product filter on a one-product board, **no timeline unless `show_target`**.

`get_roadmap()` runs one `WP_Query` per status (published items, `menu_order`), reads targets only when a list or
timeline is rendered, products/dates only when there is a toolbar, then renders `views/shortcodes/roadmap.php`
through `Utility::get_template()` (extracts `$args` keys and also leaves `$args` in scope; templates read
`$args['key'] ?? default`). Only views in `$views['render']` are put in the page; without a switcher that is the
default view, plus the list when `view_mobile_list` applies. Every item appears once per rendered view with the
same `data-task`; only the board card carries `id="nr-task-{id}"`.

The popup (`#nr-modal-overlay`) is printed once per request (`did_action( 'niroroadmap_modal_rendered' )`), the
suggest dialog once (`niroroadmap_suggest_rendered`). Tests unset these from `$GLOBALS['wp_actions']` to render again.

Board columns print every card; cards past `per_column` get `data-nr-over` (CSS hides them) and the column a
**Load more** button. The header count is always the column total.

Assets: `Front` enqueues `public/css/style.css` (+ inline settings CSS), jQuery, jQuery UI Sortable and
`public/js/script.js` on **every** front-end page. `Toolbar::enqueue()` and `Views::enqueue()` register and
enqueue `toolbar.*` / `views.*` while a board renders, only when that board needs them. `Common\Init` loads
`common/js/init.js` + `init.css` and prints the `#niroroadmap-modal` loader on every front and admin page.

The PHP templates and the JS share a DOM contract (`#nr-modal-*`, `.nr-kanban-item`, `.nr-view-item`,
`data-task`, `data-stage*`, `data-tags`, `data-tag-slugs`, `data-product-ids`, `data-date`, `data-target-sort`,
`data-pinned`, `data-nr-*`). Change both sides together; ToolbarTest and ViewsTest match the markup with regexes,
so attribute order matters.

## Front-end JS

- Globals: `NIROROADMAP` (`api_base`, `nonce`; `settings` with strings and flags added by `Front` through the `niroroadmap-localized_vars` filter, front end only), `NIROROADMAP_TOOLBAR.i18n`, `window.NiroRoadmapPaging` (`refresh( column )`, `reveal( card )`).
- `script.js`: popup with zoom animation, `GET /tasks/{id}`, votes, comments (paged, one reply level), suggest dialog with focus trap and similar-item search, editor drag and drop, Load more. All requests send `X-WP-Nonce`.
- Drag and drop: `.task-editor .nr-kanban-list` sortable; body class `task-editor` is added for `edit_pages` users. On drop it posts `/tasks/{id}/move` and `/tasks/order` (ids `nr-task-{id}` of that column). `toolbar.js` disables sorting while a search, filter or non-manual sort is active.
- `toolbar.js`: client-side only. Accent-folded search over title + tags with `<mark>` highlighting, sort within each column (pinned first), tag/product filters. State in the URL: `nr_q`, `nr_sort`, `nr_tag`, `nr_product`, suffixed `_2`, `_3` … for later toolbar boards on the page. Emits `nr:sorted` on the board element.
- `views.js`: shows one `[data-nr-view]` panel; `nr_view` (+ suffix) in the URL, `niroroadmap_view` in localStorage, list at ≤640 px when `data-nr-mobile-view`; list column sorting via `aria-sort` headers.
- Status screen: `admin/js/sorter.js` posts row ids `tag-{id}` to `/stages/order`.

## REST API (`niroroadmap/v1`)

Routes live in `Controller\Common\API::register_endpoints()`; permission callbacks in `Traits\Auth`. Handlers answer
with `Traits\Rest::response_success()` / `response_error()` — `status_header()` + `wp_send_json_*()`, which **exits**.
They never return a `WP_REST_Response`; the body is `{ "success": bool, "data": … }`. Tests therefore call
models, not handlers.

| Route | Who | Handler / notes |
|---|---|---|
| `GET /tasks/{id}` | public | `Task::get`: published, non-password items only (`Roadmap::get_public_item`); `nocache_headers()`; title, description, own `voted`, visible counts, `Fields::public_facts()`, `history` when `show_history` and >1 step |
| `POST /tasks/{id}/vote` | public | `Task::vote`: `type` upvote/downvote; 401 when `vote_who` = logged_in, 403 downvote off, 429 rate limit (30/10 min), 409 duplicate (with the recorded `vote`) |
| `GET /tasks/{id}/comments` | public | `Comment::get`: 404 when comments are off or the item isn't public; `page`, `per_page` 1–50 (20) |
| `POST /tasks/{id}/comments` | public | `Comment::create`: honeypot `website`, 5/10 min per IP except `moderate_comments`, then core `wp_handle_comment_submission()` |
| `POST /tasks/submit` | public | `Submission::create`: honeypot, login setting (401), `elapsed` ≥ min time (400), 3/hour except `edit_others_posts`, `niroroadmap_submission_verify` (403); a refused idea gives its rate-limit slot back. Response never contains the new id |
| `GET /tasks/search?q=` | public | `Submission::search`: 30/min, ≤5 published non-password items `{ id, title }` |
| `POST /tasks/{id}/move` | `can_move_task` | `edit_post` on the item + `assign_term` on the status → `wp_set_post_terms()` |
| `POST /tasks/order` | `can_order_tasks` | every `nr-task-{id}` must be an item the user can edit → `menu_order` |
| `POST /stages/order` | `can_order_stages` | every `tag-{id}` must be a status the user can edit → term meta `menu_order` |
| `GET`, `POST`, `DELETE /option` | `manage_options` | `Option`: key must start with `niroroadmap_`; `niroroadmap_settings` is read-only here |

`NIROROADMAP_SANDBOX` (constant) makes every `Auth` check pass. Never define it outside a throwaway test site.

## Voting, rate limits, privacy

- `Helper\Voter::identify()`: logged-in → HMAC of user id, no fingerprint; anonymous → HMAC of the cookie token plus a fingerprint (HMAC of IP + user agent). All hashes are `hash_hmac( 'sha256', …, wp_salt( 'auth' ) )`. Raw IP and UA are never stored. The cookie is issued on the first vote.
- `Voter::ip_hash()` keys rate limits: IPv6 by /64, IPv4-mapped IPv6 as IPv4. Only `REMOTE_ADDR` is trusted (`niroroadmap_voter_ip` for proxies).
- `Model\Vote::cast()` inserts with `INSERT IGNORE` against the unique indexes, so the database decides who voted first; vote change is an atomic compare-and-set; counters move by `GREATEST( meta + delta, 0 )`. Unknown types throw (the type becomes a meta key).
- `Helper\Rate_Limit`: fixed window in a transient, serialised per subject with MySQL `GET_LOCK`; lock not acquired → denied. `release()` refunds a slot.
- Hidden counts are left out of API responses (`Task::can_see_count()`), not just hidden in CSS. Per item: `_niroroadmap_hide_votes`.
- `Fields::public_facts()` is the only public view of item meta: cover, target (only with `show_target`), release, version, link. Effort and notes never leave the admin.
- Comments are core comments; `Comment::format()` returns plain text escaped then linked (`a`, `p`, `br` only, `nofollow ugc`), never email or IP. Held comments are shown only to their logged-in author.
- Submissions are created `pending` with the submitter in protected meta; terms are set with `wp_set_object_terms()` because the visitor has no capabilities; the notification email builds the edit link by hand.
- `Status_History` logs every `set_object_terms` change of `niroroadmap_status` on an item (editor, quick/bulk edit, REST move, imports), user 0 for code and visitors. The public timeline shows current status names and dates, never the user.

## Admin

- Menu: CPT top level "NiroRoadmap" → Items, Add New, Statuses, Products, Tags, Getting Started (`edit_pages`, slug `niroroadmap-getting-started`, menu entry removed once `niroroadmap_getting_started_done` is set), Settings (`manage_options`, `edit.php?post_type=niroroadmap_item&page=niroroadmap-settings&tab=…`).
- Getting Started: four steps computed live from the site (example items don't count); redirect once after activation (not on bulk activation, AJAX, network admin); "New here?" notice on plugin screens until done; example content via `admin-post.php?action=niroroadmap_sample_items` (`do` = add/remove, `publish_pages`), which also ensures statuses and the Roadmap page. `Sample_Data::remove()` deletes only flagged items and unused flagged terms.
- Statuses screen: color field (add/edit, nonce-checked, `sanitize_hex_color`), color column, drag to reorder.
- Item screen (`Item_Fields`): "Roadmap details" box (nonce `niroroadmap_item_fields`; `Fields::sanitize()` keeps the old value of an invalid field and queues a message in a transient, shown as a classic notice or a block editor notice — see `take_errors()` for why the `Accept` header decides), "Status history" box (last 10). Items list: sortable Target and Votes columns (`sort_list()` keeps items without the meta and any existing meta query).
- Submissions: "Submitted by" column; **Approve** row action on pending visitor submissions (`admin-post.php?action=niroroadmap_approve_submission`, nonce `niroroadmap_approve_{id}`, `publish_post`).

## Hooks

Filters: `niroroadmap_setting_{$key}`, `niroroadmap-localized_vars` (hyphen), `niroroadmap_get_posts`,
`niroroadmap_show_stage_links`, `niroroadmap_vote_fingerprint`, `niroroadmap_voter_ip`, `niroroadmap_vote_rate_limit`,
`niroroadmap_comment_rate_limit`, `niroroadmap_comment_max_length` (2000), `niroroadmap_comment_show_avatars`,
`niroroadmap_completed_status_slugs`, `niroroadmap_submission_rate_limit`, `niroroadmap_submission_verify`
(only a `WP_Error` rejects), `niroroadmap_submissions_api_available`, `niroroadmap_submission_max_title` (200),
`niroroadmap_submission_max_description` (2000), `niroroadmap_submission_min_time` (2000 ms),
`niroroadmap_submission_notify_to`, `niroroadmap_docs_url`, `niroroadmap_support_url`.

Actions: `niroroadmap_comment_created`, `niroroadmap_submission_created`; internal once-per-page markers
`niroroadmap_modal_rendered`, `niroroadmap_suggest_rendered`.

## Tests

- Integration tests against the WordPress install the plugin sits in (`tests/bootstrap.php` loads `../../../../wp-load.php`, or `WP_LOAD_PATH`) and **its real database**. Never point them at production. Each test creates and deletes its own posts, terms and users.
- WordPress must load before Composer's autoloader (`functions.php` exits without `ABSPATH`), hence `auto_prepend_file` in the `test` script.
- Classes in `tests/PHP/`: `CommentTest`, `FieldsTest`, `StatusHistoryTest`, `SubmissionTest`, `ToolbarTest`, `ViewsTest`, `VoteTest` (136 tests, 539 assertions on PHP 7.4.30 and 8.5.4).
- Run them on PHP 7.4 and the latest PHP: `php -d auto_prepend_file=tests/bootstrap.php vendor/bin/phpunit` with each binary. With Local, pass `-d mysqli.default_socket=~/.config/Local/run/<site id>/mysql/mysqld.sock`, and for 7.4 use Local's own PHP (`lightning-services/php-7.4*/bin/linux/bin/php` with its `shared-libs` on `LD_LIBRARY_PATH` and `-c` the site's `conf/php/php.ini`).
- Tests pin every setting they depend on with `niroroadmap_setting_{$key}` filters, need the default status slugs to exist, and mark the block test incomplete when `build/` is missing.

## PHP 7.4 compatibility

- No reserved word may be a namespace segment (`Trait`, `Public`, `Abstract`, `List`, `Function`, …): PHP before 8.0 can't parse it, and the whole site goes down on activation.
- No PHP 8-only syntax or functions: `match`, `?->`, named arguments, union/`mixed`/`static` types, constructor promotion, `str_contains()` / `str_starts_with()` / `str_ends_with()`.
- Sorts are not stable before PHP 8.0. Any comparator whose ties must keep an order needs an explicit tie-break (see the pinned sort in `Roadmap::get_roadmap()`); 7.4 only keeps equal items in order below 17 elements, so test with more.
- Dev packages must install on 7.4 (`config.platform.php` is `7.4`), because a site running 7.4 loads `vendor/autoload.php`, including the files of dev packages when they are installed.
- Check with `php -l` on 7.4 and the latest PHP, and run the tests on both.

## Lifecycle

| Event | Entry point | Does |
|---|---|---|
| Activation | `niroroadmap_install()` → `Installer::install()` | db version option; create both tables; find a page with `[niroroadmap]`, `[roadmap]` or the block (SQL + shortcode regex, `has_shortcode()` can't work yet) or create a published "Roadmap" page, store it in `roadmap_page`; set the redirect transient; flag statuses `pending` the first time only |
| Every load | `Activator::activate()` | register CPT/taxonomies, seed statuses on `init` 20 when `pending` and none exist, `maybe_upgrade()` |
| Deactivation | `niroroadmap_uninstall()` → `Bootstrap\Uninstaller` (misleading names) | deletes `niroroadmap_db_version` only |
| Uninstall | `uninstall.php` | always deletes the five internal options; with `delete_on_uninstall`: registers the taxonomies, force-deletes every item (meta and comments go with them), deletes all terms, drops both tables, deletes schema options and settings. The Roadmap page is always kept |

Deleting an item for good also deletes its vote rows and status log (`deleted_post` hooks in `Common\Init` and `Common\Status_History`).

## Release and CI

- Version bump: header `Version`, `NIROROADMAP_VERSION`, readme.txt `Stable tag`, changelog and Upgrade Notice. `block.json` keeps its own `0.1.0`.
- `composer release`: clean `release/` and `build/`, `npm run build:blocks`, makepot, `composer install --no-dev -o`, rsync minus `.distignore` into `release/niroroadmap/`, zip `release/niroroadmap.zip` (top-level `niroroadmap/` folder), then `composer install` again to restore dev deps.
- `.github/workflows/wp-svn-release.yml`: a pushed `v*` tag checks header and Stable tag, installs `--no-dev`, builds the block (fails without `build/blocks-manifest.php`), syncs trunk (`.distignore`) and `svn-assets/` → SVN `assets/`, copies trunk to `tags/<version>` and commits. It refuses an existing SVN tag. Pushing a tag publishes to every user.
- `.distignore` keeps `CLAUDE.md`, `.claude/`, tests, `svn-assets/`, `readme.md` and build tooling out of the zip and SVN.
- `svn-assets/blueprints/blueprint.json` (Playground demo, linked from readme.md) installs the WordPress.org release and calls `Settings::update()`, `Model\Fields` and `Model\Status_Log` directly — renaming those breaks the demo.

## Docs to keep in sync

`readme.txt` (WordPress.org: features, settings, shortcode attributes, hooks, REST, privacy, FAQ, changelog) and
`readme.md` (GitHub: shortcode table, REST table, developer notes). New settings, attributes, hooks, routes or stored
data change both. Never put issue or PR numbers in the changelog.

## Known gaps (verified in the code, not fixed)

- Dead code: `niroroadmap_settings_menus()`, `niroroadmap_product_post_type()`, `niroroadmap_home_url()`; `Utility::format_date()`, `pri()`, `get_option()`; `Traits\Rest::register_route()`; traits `Cache` and `Limiter`; `Activator::set_cron()` is empty; `use NiroRoadmap\Model\Database` in Installer and Uninstaller names a class that doesn't exist; `assets/admin/js/init.js`, `settings.js`, `css/settings.css` and the `#niroroadmap-settings-*` rules in `admin/css/style.css` belong to an old settings UI.
- `script.js` always sends `X-WP-Nonce`. Core's `rest_cookie_check_errors()` rejects an invalid nonce with 403 even for logged-out visitors, so a page served from a cache older than the nonce lifetime breaks votes, comments and suggestions, despite the "works on cached pages" comment in `API\Submission`.
- The board query doesn't exclude password-protected items, but the popup API does (404 "Task not found").
- The custom CSS description says it loads only on roadmap pages; `Front::add_assets()` loads it with `style.css` and `script.js` on every front-end page.
- `script.js` has two untranslated strings: "Loading…" and "Could not load details.".
