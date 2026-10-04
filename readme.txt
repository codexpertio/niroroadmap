=== NiroRoadmap - Public Product Roadmap & Kanban Board ===
Contributors: nirosuite
Tags: roadmap, kanban, product roadmap, feedback, voting
Requires at least: 6.0
Tested up to: 7.1
Requires PHP: 8.0
Stable tag: 0.9.2
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Share a public product roadmap as a clean Kanban board. Visitors browse what's planned, in progress and shipped, and vote on what matters.

== Description ==

**NiroRoadmap** turns your WordPress site into a public product roadmap. Add roadmap items, group them into statuses such as *Planned*, *In Progress* and *Completed*, and publish the board on any page with a shortcode or block.

Visitors can open any item for the full details and upvote or downvote it. You find out what your users care about most, and they can see what you're working on.

= Features =

* **Kanban board:** Items are grouped into status columns, each with its own accent color and item count.
* **Item details popup:** Clicking an item opens a popup with its status, tags, description and votes. It zooms out of the card in the style of macOS Quick Look.
* **Voting:** Anyone can upvote or downvote an item, with no account needed. Each browser can vote once per item.
* **Drag and drop:** Editors and administrators can drag items between columns and reorder them right on the public board.
* **Sortable statuses:** Drag statuses into the order you want on the admin screen. The public board uses the same order.
* **Products:** Run separate roadmaps for several products and show one product per board.
* **Tags:** Label items (e.g. *Payments*, *Checkout*). Tags are shown on each card.
* **Item details:** Give an item a target (a date or a quarter like *Q4 2026*), a release date and version, a link, a cover image (its Featured image), an effort size and private notes for your team. Pin an item to the top of its column, or hide its vote counts.
* **Status history:** See who moved an item to which status and when, and optionally show visitors the path an item took.
* **Shortcode and block:** Use the `[niroroadmap]` shortcode or the **Roadmap** block.
* **Responsive:** On phones the columns scroll sideways with snapping.
* **Accessible:** Cards can be opened with the keyboard, Esc closes the popup, and the animation is replaced by a plain fade for visitors who prefer reduced motion.
* **Theme-friendly:** Colors, borders and spacing are CSS custom properties you can override from your theme.

= Getting started =

On activation, NiroRoadmap creates a **Roadmap** page and four statuses (Under Review, Planned, In Progress, Completed), so the board works straight away.

1. Adjust the columns under **NiroRoadmap → Statuses** if you like. Rename them, pick colors, and drag them into order.
2. Optionally add your products under **NiroRoadmap → Products** and your labels under **NiroRoadmap → Tags**.
3. Add items under **NiroRoadmap → Add New**. Give each one a status, and optionally a product and tags.
4. Visit the **Roadmap** page. To show the board somewhere else, use the `[niroroadmap]` shortcode or the **Roadmap** block.

= Shortcode =

`[niroroadmap]` shows every item.

`[niroroadmap product="12"]` shows only the items of one product. Use the term ID from **NiroRoadmap → Products**.

`[niroroadmap toolbar="yes" sort="votes" filters="search,tag"]` adds a search, sort and filter toolbar. `toolbar` is `yes` or `no`; `sort` is `manual`, `votes`, `newest`, `oldest` or `commented`; `filters` is a comma list of `search`, `tag` and `product` (or `none`). Without these options a board follows **Settings → Toolbar**. The Roadmap block has the same options in its sidebar.

`[niroroadmap submissions="yes"]` shows a "Suggest an idea" button on that board even when it is off site-wide; `submissions="no"` hides it.

The older `[roadmap]` shortcode still works.

= For developers =

Filters:

* `niroroadmap_show_stage_links`: return `true` to link each column title to its status archive.
* `niroroadmap_get_posts`: filter the items loaded for a column.
* `niroroadmap-localized_vars`: filter the variables passed to the front-end scripts.

The board's look can be changed by overriding these CSS custom properties on `.nr-kanban-columns` and `.nr-modal-overlay`: `--nr-bg`, `--nr-card`, `--nr-border`, `--nr-text`, `--nr-muted`, `--nr-accent`, `--nr-radius`.

Roadmap data is stored as a regular custom post type (`niroroadmap_item`) with the taxonomies `niroroadmap_status`, `niroroadmap_product` and `niroroadmap_tag`, so it works with the standard WordPress APIs.

= Source code and build tools =

The full source code is publicly available at [github.com/codexpertio/niroroadmap](https://github.com/codexpertio/niroroadmap).

The only compiled file is the block script in `build/roadmap/`. Its human-readable source ships with the plugin in `app/Blocks/roadmap/`. To rebuild it, run `npm install` and then `npm run build:blocks` (uses [@wordpress/scripts](https://www.npmjs.com/package/@wordpress/scripts)). PHP dependencies are listed in `composer.json`; run `composer install` to install them.

= Privacy =

NiroRoadmap makes no requests to external services. To allow one vote per visitor per item, it recognises voters like this:

* Logged-in users are identified by user ID.
* Other visitors get a random token in a first-party cookie, `niroroadmap_voter` (1 year, `SameSite=Lax`, `HttpOnly`, `Secure` on HTTPS), set when they first vote. They are also recognised by a salted hash (HMAC-SHA256, keyed with your site's WordPress salts) of their IP address and browser user agent, so clearing cookies doesn't reset their votes.

The vote table stores only these hashes, the item, the vote type and the time. The raw IP address and user agent are never stored. A salted hash is still pseudonymous data, so mention it in your privacy policy. To stop using IP addresses at all, return an empty string from the `niroroadmap_vote_fingerprint` filter; votes are then recognised by cookie only. Votes are also shown as plain counts on each item, and the visitor's browser keeps a list of the items it has voted on in local storage (`niroroadmap_votes`) to display the buttons correctly. That list is never sent to the server. A short-lived counter keyed by a hash of the IP address limits how fast votes can be sent. Vote data is deleted when you uninstall with "Delete all data" turned on.

When comments are turned on, a comment is a standard WordPress comment. WordPress stores the commenter's name, email address, IP address and browser user agent with it, the same as on any post, and your spam plugin may send these to its own service. The roadmap board never shows email addresses or IP addresses. It does show each commenter's avatar, which for people without a profile picture is a Gravatar image whose address contains a hash of their email; return false from the `niroroadmap_comment_show_avatars` filter to turn avatars off. A hidden form field and a per-IP rate limit (using a hash of the IP address, as for votes) help keep out spam. Comments are deleted with their item, and when you uninstall with "Delete all data" turned on.

When visitors can suggest ideas, an idea is stored as a pending item, like any other. If you ask for a name and email, or the person is logged in, those are stored privately with the idea so you can follow up. They are shown only in the admin Items list, and never on the board or in the public API. The idea itself becomes public only when you publish it. A hidden form field, a minimum fill-in time and a per-IP rate limit (using a hash of the IP address, as for votes) help keep out spam, and a filter lets you add a CAPTCHA. If you turn on the email notification, the idea and the submitter's name and email are sent to the address you choose. Ideas, and the name and email stored with them, are deleted with the item, and when you uninstall with "Delete all data" turned on.

Item details: the target is shown to visitors only if you turn that on in **Settings → General**. The release date, version, link and cover image are shown when filled in. The effort size and the internal notes are for your team only: they are never shown on the board or returned by the public API.

Status history: each time an item moves to another status, the plugin records which status, when, and the ID of the user who moved it (0 when it was done by code or by a visitor's submission). Only your team sees who; the optional timeline shown to visitors has statuses and dates only. The history is deleted with the item, and when you uninstall with "Delete all data" turned on.

== Installation ==

1. In your dashboard, go to **Plugins → Add New**, search for "NiroRoadmap", then install and activate it. Or upload the `niroroadmap` folder to `/wp-content/plugins/` and activate it from the **Plugins** screen.
2. A **Roadmap** page and default statuses are created for you. If a page already shows the roadmap, it's used instead of creating a new one.
3. Add items under **NiroRoadmap → Add New**.

== Frequently Asked Questions ==

= Do visitors need an account to vote? =

No. Anyone can upvote or downvote an item. Each browser can vote once per item.

= Who can drag items between columns? =

Editors and administrators. They can drag items directly on the public board while logged in. Everyone else sees a read-only board.

= How do I change the order of the columns? =

Go to **NiroRoadmap → Statuses** and drag the rows into the order you want. The order is saved right away and used on the public board.

= How do I change a column's color? =

Edit the status under **NiroRoadmap → Statuses** and pick a color. It's used for the column's top border and status dot.

= Can I show a separate roadmap per product? =

Yes. Assign items to a product, then use `[niroroadmap product="ID"]` with that product's term ID.

= Will the board match my theme? =

It uses your theme's fonts and a neutral design. To adjust the colors, override the CSS custom properties listed under "For developers".

= Will activating it create pages or statuses every time? =

No. The Roadmap page is created once, and only if no page already shows the roadmap. The default statuses are added once, and only if you have none. If you delete them, they won't come back.

= What happens to my data if I delete the plugin? =

Deleting the plugin removes its settings. Your roadmap items, statuses, products, tags and the Roadmap page are kept.

== Screenshots ==

1. The public roadmap board, with status columns, tags and vote counts.
2. The item popup with status, tags, voting and the full description.
3. Managing statuses. Drag the rows to reorder the columns and pick a color for each.

== Changelog ==

= 0.9 - 2026-09-26 =
* Initial release with Kanban board, task voting, shortcode support, REST API integration, and customizable taxonomy columns.

== Upgrade Notice ==
