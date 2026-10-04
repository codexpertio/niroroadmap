=== NiroRoadmap - Public Product Roadmap & Kanban Board ===
Contributors: nirosuite
Tags: roadmap, kanban, feedback, voting, feature requests
Requires at least: 6.0
Tested up to: 7.1
Requires PHP: 8.0
Stable tag: 0.9.2
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Share a public product roadmap as a Kanban board, a list or a timeline. Visitors see what's planned and shipped, vote, comment and suggest ideas.

== Description ==

**NiroRoadmap** turns your WordPress site into a public product roadmap. Add roadmap items, group them into statuses such as *Planned*, *In Progress* and *Completed*, and publish the roadmap on any page with a shortcode or block, as a Kanban board, a sortable list or a timeline by quarter.

Visitors can open any item for the full details, upvote or downvote it, join the discussion and suggest ideas of their own. You find out what your users care about most, and they can see what you're working on.

Everything is optional and off by default where it matters: comments, idea submissions, the search toolbar and the view switcher are switched on from **NiroRoadmap → Settings**, and each board can override them with a shortcode attribute or block option.

= Features =

* **Kanban board:** Items are grouped into status columns, each with its own accent color and item count.
* **Item details popup:** Clicking an item opens a popup with its status, tags, description and votes. It zooms out of the card in the style of macOS Quick Look.
* **Voting:** Anyone can upvote or downvote an item, with no account needed. One vote per visitor per item, enforced on the server. Optionally require a login, let voters change their vote, hide the downvote button, or show downvote counts to administrators only.
* **Comments:** Visitors discuss an item right in its popup, with threaded replies and a *Team* badge on staff replies. Comments are normal WordPress comments, so your moderation tools and spam plugins apply. Require a login, choose the order, and close comments automatically when an item is completed.
* **Suggest an idea:** A *Suggest an idea* button opens a short form. While the visitor types a title, similar existing items are listed so duplicates are caught early. Ideas arrive as *Pending* items, are never public until you approve them, and can notify you by email. A one-click **Approve** action is added to the Items list.
* **Search, sort and filter toolbar:** Visitors search the board and sort by most votes, newest, oldest or most commented. They can filter by tag and product too. The toolbar loads no extra script or styles on boards that don't use it.
* **Board, List and Timeline views:** Show the roadmap as Kanban columns, a compact sortable table, or a timeline grouped by quarter, month or *Now / Next / Later*. A switcher lets visitors choose, and their choice is remembered and can be shared in the page address. Boards that don't use the extra views load no extra script or styles.
* **Drag and drop:** Editors and administrators can drag items between columns and reorder them right on the public board.
* **Sortable statuses:** Drag statuses into the order you want on the admin screen. The public board uses the same order.
* **Products:** Run separate roadmaps for several products and show one product per board.
* **Tags:** Label items (e.g. *Payments*, *Checkout*). Tags are shown on each card.
* **Item details:** Give an item a target (a date or a quarter like *Q4 2026*), a release date and version, a link, a cover image (its Featured image), an effort size and private notes for your team. Pin an item to the top of its column, or hide its vote counts.
* **Status history:** See who moved an item to which status and when, and optionally show visitors the path an item took.
* **Shortcode and block:** Use the `[niroroadmap]` shortcode or the **Roadmap** block.
* **Settings page:** Eight tabs (General, Voting, Comments, Submissions, Toolbar, Views, Appearance, Advanced) control everything without code.
* **Appearance:** Light, dark or automatic color scheme, an accent color, the corner radius and your own custom CSS.
* **Responsive:** On phones the columns scroll sideways with snapping and the toolbar collapses into a *Sort & filter* button. The list view can be the default on narrow screens.
* **Accessible:** Cards can be opened with the keyboard, Esc closes the popup, and the animation is replaced by a plain fade for visitors who prefer reduced motion.
* **Theme-friendly:** Colors, borders and spacing are CSS custom properties you can override from your theme.
* **Developer-friendly:** A REST API under `niroroadmap/v1`, a dozen filters and actions, and a regular custom post type.

= Feature details =

**The board**

Each status is a column with its own color, a count of its items and a card for every item. A card shows the title, its tags, how many comments it has and its vote count. Pinned items stay at the top of their column. Items can be limited to one product, and the board uses your theme's fonts and a neutral look, in light, dark or automatic colors.

**Item popup**

Clicking a card, or pressing Enter on it, opens a popup with the status, tags, title, voting buttons, the full description and the discussion. If you filled them in, it also shows the cover image (the item's Featured image), the target, the release date and version and a link. With **Settings → General → status timeline** on, it also shows the path the item took, for example "Under Review → Planned → In Progress", with dates.

**Voting**

Votes are counted per visitor, not per click. A logged-in user is recognised by their account and anyone else by a cookie and a salted hash, so clearing cookies does not give a visitor a new vote. Voters can optionally be allowed to switch an up vote to a down vote and back. You can turn off downvotes completely, hide downvote counts from visitors, or hide the vote counts of a single item. A visitor who is not allowed to vote sees a prompt to log in.

**Comments**

Comments are saved as ordinary WordPress comments on the item, so Settings → Discussion, the Comments screen and your spam plugin all apply. Guests are asked for a name and email (the email is never shown), logged-in users are not. Visitors can reply to a comment, and a reply sits under its parent. Comments from users who can edit posts get a *Team* badge. A comment held for moderation is shown to its author as awaiting moderation. Long threads load a page at a time.

**Suggest an idea**

The button opens a dialog with a title, optional details, an optional product and (as you choose) a name and email. Before it is sent, the dialog searches existing items and lists any that look similar, so visitors can vote for them instead. A new idea is saved as *Pending* with the status you chose, shows **Submitted by** in the Items list and waits for your **Approve**. The submitter's upvote can be counted automatically, and an email can tell you about each new idea.

**Search, sort and filter**

The toolbar searches card titles (ignoring accents and capital letters) and highlights the match. Visitors can sort inside each column and filter by tag and product. The current search, sort and filters are kept in the page address, so a filtered board can be bookmarked or shared. Editors can't drag cards while a sort or filter is active, so a view never saves a wrong order by accident.

**Board, List and Timeline**

The **Board** is the Kanban view. The **List** is a compact table of title, status, tags, comments, votes and target, and its headings sort it. The **Timeline** groups items by their target: by quarter (Q4 2026, Q1 2027 …), by month, or as *Now / Next / Later*. Items without a target are listed last under *Later / Unscheduled*, and a group with no items is left out. All three views show the same items, honor the same search and filters, and open the same popup, so voting and comments work everywhere. Dragging stays on the Board. The timeline needs **Show an item's target date or quarter** (General tab), because it would otherwise reveal targets you keep private.

**Roadmap details on each item**

Target (a date such as *2026-12-31* or a quarter such as *Q4 2026*), release date, version, link, effort (XS, S, M, L, XL), private notes, *Pin to the top of its column* and *Hide votes*. The target is shown only if you turn it on. Effort and notes are only for your team.

**Status history**

Whenever an item changes status, from the edit screen or by dragging on the board, and when an idea is submitted with its starting status, the plugin records the old and new status, the time and who did it. The item edit screen lists the history, and the optional visitor timeline shows statuses and dates only.

**Blocks and shortcodes**

The **Roadmap** block and the `[niroroadmap]` shortcode take the same options: product, toolbar, sort, filters, view, switcher, group and submissions. You can place several boards on one site, each with its own settings.

= Getting started =

On activation, NiroRoadmap creates a **Roadmap** page and four statuses (Under Review, Planned, In Progress, Completed), so the board works straight away.

1. Adjust the columns under **NiroRoadmap → Statuses** if you like. Rename them, pick colors, and drag them into order.
2. Optionally add your products under **NiroRoadmap → Products** and your labels under **NiroRoadmap → Tags**.
3. Add items under **NiroRoadmap → Add New**. Give each one a status, and optionally a product, tags and the **Roadmap details** (target, release date and version, link, effort, pin).
4. Open **NiroRoadmap → Settings** and turn on what you want: comments, the *Suggest an idea* button, the search toolbar, a dark color scheme.
5. Visit the **Roadmap** page. To show the board somewhere else, use the `[niroroadmap]` shortcode or the **Roadmap** block.

= Settings =

Find them under **NiroRoadmap → Settings**.

* **General:** the Roadmap page, a default product, linking status names to their archive, and what the board shows: vote counts, tags, the downvote button, an item's target and its status timeline.
* **Voting:** who can vote (everyone or logged-in users), whether voters may change their vote, and who sees downvote counts.
* **Comments:** turn comments on, require login, choose the order, and close comments when an item is moved to *Completed*.
* **Submissions:** turn the *Suggest an idea* button on, require login, ask for name and email (not asked, optional or required), pick the status new ideas start in, count the submitter's own upvote, and set the notification email.
* **Toolbar:** turn the toolbar on, pick the initial sort and which filters to offer (search, tag, product).
* **Views:** the default view, whether visitors get the Board / List / Timeline switcher, opening boards as a list on narrow screens, and how the timeline groups items.
* **Appearance:** light, dark or auto color scheme, accent color, corner radius and custom CSS.
* **Advanced:** delete all plugin data on uninstall, and reset every setting to its default.

= Managing ideas and item details =

* Ideas visitors suggest appear in **NiroRoadmap → All Items** as *Pending*, with a **Submitted by** column. Click **Approve** to publish one, or edit it first.
* Each item has a **Roadmap details** box with its target (a date or a quarter like *Q4 2026*), release date, version, link, effort size (XS to XL), private team notes, and switches to hide its votes or pin it to the top of its column.
* A **Status history** box on the item screen shows every status change, who made it and when.
* The Items list has **Target** and **Votes** columns.

= Shortcode =

`[niroroadmap]` shows every item.

`[niroroadmap product="12"]` shows only the items of one product. Use the term ID from **NiroRoadmap → Products**.

`[niroroadmap toolbar="yes" sort="votes" filters="search,tag"]` adds a search, sort and filter toolbar. `toolbar` is `yes` or `no`; `sort` is `manual`, `votes`, `newest`, `oldest` or `commented`; `filters` is a comma list of `search`, `tag` and `product` (or `none`). Without these options a board follows **Settings → Toolbar**. The Roadmap block has the same options in its sidebar.

`[niroroadmap view="timeline" switcher="yes" group="month"]` opens the board as a timeline and lets visitors switch views. `view` is `board`, `list` or `timeline`; `switcher` is `yes` or `no`; `group` is `quarter`, `month` or `nownext` (Now / Next / Later). Without these options a board follows **Settings → Views**. Visitors can share a link to a view with `?nr_view=list` in the address. The Roadmap block has the same options in its sidebar.

`[niroroadmap submissions="yes"]` shows a "Suggest an idea" button on that board even when it is off site-wide; `submissions="no"` hides it.

Options combine, for example `[niroroadmap product="12" toolbar="yes" sort="votes" submissions="yes"]`.

The older `[roadmap]` shortcode still works.

= For developers =

Filters:

* `niroroadmap_setting_{$key}`: filter any setting value, e.g. `niroroadmap_setting_comments_enabled`.
* `niroroadmap_show_stage_links`: return `true` to link each column title to its status archive.
* `niroroadmap_get_posts`: filter the items loaded for a column.
* `niroroadmap-localized_vars`: filter the variables passed to the front-end scripts.
* `niroroadmap_vote_fingerprint`, `niroroadmap_voter_ip`: change or disable how anonymous voters are recognised.
* `niroroadmap_vote_rate_limit`, `niroroadmap_comment_rate_limit`, `niroroadmap_submission_rate_limit`: change the rate limits (`array( count, seconds )`).
* `niroroadmap_comment_max_length`, `niroroadmap_comment_show_avatars`: limit comment length, or hide avatars.
* `niroroadmap_completed_status_slugs`: the status slugs that count as completed for closing comments (default `completed`).
* `niroroadmap_submission_verify`: return `false` or a `WP_Error` to reject an idea, for example to add a CAPTCHA.
* `niroroadmap_submission_max_title`, `niroroadmap_submission_max_description`, `niroroadmap_submission_min_time`: limits for the suggestion form.
* `niroroadmap_submission_notify_to`: change the recipient of the new-idea email.

Actions:

* `niroroadmap_comment_created`: fires after a visitor's comment is saved.
* `niroroadmap_submission_created`: fires after an idea is submitted.

REST API (namespace `niroroadmap/v1`): `GET /tasks/{id}`, `POST /tasks/{id}/vote`, `GET` and `POST /tasks/{id}/comments`, `POST /tasks/submit` and `GET /tasks/search` are public and rate limited. Moving and ordering items and statuses (`/tasks/{id}/move`, `/tasks/order`, `/stages/order`) need an editor or administrator.

The board's look can be changed by overriding these CSS custom properties on `.nr-kanban-columns` and `.nr-modal-overlay`: `--nr-bg`, `--nr-card`, `--nr-border`, `--nr-text`, `--nr-muted`, `--nr-accent`, `--nr-radius`.

Helper: `niroroadmap_get_setting( $key )` returns a plugin setting.

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

No, unless you want them to. Anyone can upvote or downvote an item, and each visitor gets one vote per item. To require a login, change **Settings → Voting → Who can vote**.

= Can visitors comment? =

Yes, when you turn on **Settings → Comments**. Comments show in the item popup and are moderated like any WordPress comment. You can require a login, close comments on one item from its **Discussion** box, or close them automatically when an item is completed.

= Can visitors suggest ideas? =

Yes, when you turn on **Settings → Submissions**, or add `submissions="yes"` to one board. Ideas are saved as *Pending* and stay hidden until you approve them from the Items list. Visitors see similar items while they type, which cuts down on duplicates.

= How do I stop spam on comments and ideas? =

Both forms use a hidden honeypot field, a per-IP rate limit and (for ideas) a minimum fill-in time. Comments also pass through your usual WordPress moderation and spam plugins. To add a CAPTCHA to the idea form, use the `niroroadmap_submission_verify` filter.

= Can I use a dark theme? =

Yes. Choose *Dark* or *Auto* (follows the visitor's device) under **Settings → Appearance**.

= Who can drag items between columns? =

Editors and administrators. They can drag items directly on the public board while logged in. Everyone else sees a read-only board.

= How do I change the order of the columns? =

Go to **NiroRoadmap → Statuses** and drag the rows into the order you want. The order is saved right away and used on the public board.

= How do I change a column's color? =

Edit the status under **NiroRoadmap → Statuses** and pick a color. It's used for the column's top border and status dot.

= Can I show a separate roadmap per product? =

Yes. Assign items to a product, then use `[niroroadmap product="ID"]` with that product's term ID. To pick the product for boards that don't name one, set **Settings → General → Default product**.

= Where do I manage my roadmap items? =

Under **NiroRoadmap → All Items**. Add or edit an item like any post: give it a title, a description, a status, and optionally a product, tags, a Featured image (shown as its cover) and the **Roadmap details**.

= How do I show the roadmap on another page? =

Add the **Roadmap** block, or a Shortcode block with `[niroroadmap]`. The page chosen in **Settings → General → Roadmap page** is just the page the plugin created for you, so you can also build your own and ignore it.

= What can I enter as an item's target? =

A date written `YYYY-MM-DD` (for example `2026-12-31`) or a quarter such as `Q4 2026` (also `q4-2026`). Anything else is rejected with a message and the old value is kept. Visitors see the target only if you turn on **Settings → General → Show an item's target date or quarter in its popup**.

= How do I show visitors where an item is in its journey? =

Turn on **Settings → General → Show an item's status timeline in its popup**. Visitors see statuses and dates. Only your team ever sees who moved the item.

= Is the effort size or the internal notes visible to visitors? =

No. They are for your team and are never shown on the board or returned by the public API.

= Can I pin an item or hide its votes? =

Yes. Use the *Pin to the top of its column* and *Hide votes* switches in the **Roadmap details** box of the item. Pinned items stay at the top of their column whatever the sort order.

= Can someone vote twice by clearing their cookies? =

Not easily. Besides the cookie, votes are matched by a salted hash of the visitor's IP address and browser. If you prefer to use cookies only, return an empty string from the `niroroadmap_vote_fingerprint` filter. See "Privacy" below.

= Can voters take their vote back or change it? =

When **Settings → Voting → Let voters change their vote** is on, a voter can switch between an up vote and a down vote. A vote can't be withdrawn. When the setting is off, a vote is final.

= Can I turn off downvotes? =

Yes. Turn off **Settings → General → Show the downvote button**. The server then rejects downvotes too. To keep downvotes but hide the numbers, use **Settings → Voting → Show downvote counts to**.

= Who can comment, and do they need to enter an email? =

Anyone, unless you turn on **Require login to comment** (or WordPress requires registered users to comment). Guests give a name and email; the email is kept private. Logged-in users aren't asked.

= How do I moderate comments? =

Like any other WordPress comments, from the **Comments** screen. Roadmap comments follow your Settings → Discussion rules (for example "hold for moderation"), and a visitor sees their own held comment as awaiting moderation. Roadmap comments are also open to your spam plugin.

= How do I close comments on one item? =

Open the item and use its **Discussion** box on the edit screen. To close them automatically, turn on **Settings → Comments → Close comments when an item is moved to Completed**.

= Do comments have replies? =

Yes, one level deep: a visitor can reply to a comment, but not to a reply.

= Where do the ideas visitors suggest go? =

They are saved as *Pending* items. Open **NiroRoadmap → All Items**, look at the **Submitted by** column and click **Approve** to publish one, or edit it first. A published idea appears on the board in the status you picked under **Settings → Submissions → Status for new ideas**.

= Do I get an email when someone suggests an idea? =

Yes, by default, at the site's admin address. Change the address, or turn it off, under **Settings → Submissions**. The `niroroadmap_submission_notify_to` filter can change the recipient from code.

= Can I stop anonymous visitors from suggesting ideas? =

Yes. Turn on **Settings → Submissions → Only logged-in users can suggest ideas**. You can also set **Ask visitors for their name and email** to *Don't ask*, *Ask, but optional* or *Ask, and require both*.

= Can visitors choose a product for their idea? =

Yes, when you have products, the dialog shows a **Product** field. On a board limited to one product, the idea is filed under that product automatically.

= What limits are there on comments and ideas? =

By default a comment can be 2000 characters, an idea title 200 and its details 2000. A visitor can send 5 comments per 10 minutes and 3 ideas per hour, and 30 votes per 10 minutes. An idea can't be sent within 2 seconds of the dialog opening. Developers can change all of these with filters (see "For developers").

= Does the search work with accents? =

Yes. It ignores accents and capital letters, so "cafe" finds "Café".

= Can I share a link to a filtered board? =

Yes. The search, sort, filters and the chosen view (Board, List or Timeline) are kept in the page address, so copy the address from your browser.

= Why can't I drag items while a sort or search is active? =

A sorted or filtered view doesn't show every item in its manual order, so saving a drag would store a misleading order. Clear the search or put the sort back on *Manual order* to drag again.

= Does the board work on phones? =

Yes. The columns scroll sideways with snapping, and the toolbar collapses into a **Sort & filter** button. The list and timeline views stack to fit a narrow screen, and **Settings → Views → Open boards as a list on narrow screens** makes the list the first thing phone visitors see.

= Is it accessible? =

Cards can be opened with the keyboard, the popup and the dialogs can be closed with Esc, vote and toolbar messages are announced to screen readers, and the zoom animation becomes a plain fade for visitors who prefer reduced motion.

= Can I translate it? =

Yes. The text domain is `niroroadmap` and a template file is in the `languages` folder. You can also translate it from translate.wordpress.org once the plugin is listed there.

= Can I add my own CSS? =

Yes. Paste it into **Settings → Appearance → Custom CSS**, or override the CSS custom properties listed under "For developers" from your theme. The accent color and corner radius have their own settings.

= Which users can change things? =

Administrators change the settings. Editors and administrators can edit items and drag them on the board. Visitors can vote, and comment or suggest ideas when you allow it.

= Does it send data to other services or set cookies? =

It makes no requests to outside services. A first-party cookie is set the first time a visitor votes. See "Privacy" for the details. Comment avatars use Gravatar like the rest of WordPress, unless you turn them off.

= Is there a REST API? =

Yes, under `/wp-json/niroroadmap/v1/`. See "For developers" for the routes.

= Will the board match my theme? =

It uses your theme's fonts and a neutral design. To adjust the colors, override the CSS custom properties listed under "For developers".

= Can I show the roadmap as a list or a timeline? =

Yes. Turn on **Settings → Views → Let visitors switch between Board, List and Timeline**, or use `[niroroadmap switcher="yes"]` on one board. To show a view without a switcher, use `[niroroadmap view="list"]` or `view="timeline"`. The timeline groups items by their target, so turn on **Show an item's target date or quarter** in the General tab first.

= Can I add a search box and sorting to the board? =

Yes. Turn on **Settings → Toolbar**, or use `[niroroadmap toolbar="yes"]` on one board. Visitors can then search, sort by votes, newest, oldest or most commented, and filter by tag and product.

= Can I reset the settings? =

Yes. **Settings → Advanced → Reset to defaults** restores every setting on all tabs. Your items, statuses, products and tags are not affected.

= Will activating it create pages or statuses every time? =

No. The Roadmap page is created once, and only if no page already shows the roadmap. The default statuses are added once, and only if you have none. If you delete them, they won't come back.

= What happens to my data if I delete the plugin? =

By default, nothing but a few internal options is removed: your roadmap items (with their votes and comments), statuses, products, tags, settings and the Roadmap page are kept. To remove everything, turn on **Settings → Advanced → Delete all data when the plugin is uninstalled** before you delete the plugin. The Roadmap page is always kept.

== Screenshots ==

1. The public roadmap board, with status columns, tags, comment and vote counts, a search and sort toolbar, the Board / List / Timeline switcher and the "Suggest an idea" button.
2. The list view: a compact table of ideas with status, tags, comments, votes and target. Click a heading to sort.
3. The timeline view, with items grouped by the quarter they are planned for and the unscheduled ones last.
4. The item popup with status, tags, voting, the full description, the target and the discussion.
5. Comments in the item popup, with a Team badge on staff comments, and the comment form.
6. The "Suggest an idea" form. Similar existing items are listed while the visitor types a title, to catch duplicates.
7. Searching the board. Matches are highlighted and empty columns say so.
8. The board on a phone: the view switcher sits under the search, and columns scroll sideways.
9. The list view on a phone.
10. Managing statuses. Drag the rows to reorder the columns and pick a color for each.

== Changelog ==

= Unreleased =
* New: List and Timeline views alongside the Kanban board, with a view switcher, a remembered choice, shareable `?nr_view=` links, and `view`, `switcher` and `group` shortcode attributes and block options.
* New: Views tab in the settings: default view, switcher, list on narrow screens, and timeline grouping (quarter, month or Now / Next / Later).
* New: Settings page with General, Voting, Comments, Submissions, Toolbar, Appearance and Advanced tabs.
* New: comments in the item popup, with threaded replies, moderation, rate limiting and auto-close on completion.
* New: "Suggest an idea" form with similar-item suggestions, pending review, one-click approval and email notification.
* New: search, sort and filter toolbar, also available as shortcode attributes and block options.
* New: item details (target, release date and version, link, effort, private notes, pin, hide votes) and a status change history with an optional visitor timeline.
* New: light, dark and auto color schemes, accent color, corner radius and custom CSS.
* Improved: one vote per visitor per item is now enforced on the server.

= 0.9 - 2026-09-26 =
* Initial release with Kanban board, task voting, shortcode support, REST API integration, and customizable taxonomy columns.

== Upgrade Notice ==
