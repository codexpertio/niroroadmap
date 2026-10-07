# NiroRoadmap 🚀

**A public product roadmap for WordPress. Show what is planned, in progress and shipped as a Kanban board, a list or a timeline, and let your users vote, comment and suggest ideas.**

[Homepage](https://nirosuite.com/niroroadmap) · [WordPress.org](https://wordpress.org/plugins/niroroadmap/) · [Support](https://support.nirosuite.com) · [Releases](https://github.com/codexpertio/niroroadmap/releases)

**[Try the live demo](https://playground.wordpress.net/?blueprint-url=https://raw.githubusercontent.com/codexpertio/niroroadmap/master/svn-assets/blueprints/blueprint.json)** in WordPress Playground, with sample items, votes and comments.

![NiroRoadmap Kanban board](https://github.com/user-attachments/assets/3f7a882a-794b-4ad8-830f-037dbfb91e4a)

## 📌 Features

- **Board, List and Timeline views** with an optional view switcher. The timeline groups items by quarter, month or *Now / Next / Later*.
- **Voting** with one vote per visitor per item, enforced on the server. No account needed, or require a login.
- **Comments** in each item's popup, built on native WordPress comments, so moderation and spam plugins keep working.
- **Suggest an idea** form with duplicate detection. Ideas arrive as *Pending* items and stay hidden until you approve them.
- **Search, sort and filter toolbar** (by votes, newest, oldest, most commented; by tag and product).
- **Drag and drop** for editors, on the public board and in the admin, for items and for statuses.
- **Products and tags** for separate roadmaps and labels.
- **Item details**: target date or quarter, release date and version, link, cover image, effort size, private team notes, pinning.
- **Status history** that records every change, with an optional public timeline.
- **Settings page** with eight tabs (General, Voting, Comments, Submissions, Toolbar, Views, Appearance, Advanced). Light, dark or auto color scheme, accent color, corner radius and custom CSS.
- **Shortcode and Roadmap block**, with the same options.
- **REST API** under `niroroadmap/v1`, plus filters and actions for developers.
- **Responsive and accessible**: keyboard navigation, reduced-motion support, and a list view for narrow screens.

## 📦 Installation

### From WordPress.org

1. Go to **Plugins → Add New**, search for **NiroRoadmap**, then click **Install Now** and **Activate**.

### From a release `.zip`

1. Download the latest `.zip` from [Releases](https://github.com/codexpertio/niroroadmap/releases).
2. Go to **Plugins → Add New → Upload Plugin**, choose the file, then **Install Now** and **Activate**.

### From GitHub (for development)

The block script is built from source and is not stored in the repository.

```bash
git clone https://github.com/codexpertio/niroroadmap.git
cd niroroadmap
composer install --no-dev
npm install
npm run build:blocks
```

Then put the `niroroadmap` folder in `wp-content/plugins/` and activate it.

## 🚀 Getting started

On activation, NiroRoadmap creates a **Roadmap** page and four statuses (Under Review, Planned, In Progress, Completed), then opens a **Getting Started** screen with a checklist:

1. **Set up your columns**: rename the statuses, pick colors, drag them into order (**NiroRoadmap → Statuses**).
2. **Add your first item**: give it a title, a description and a status (**NiroRoadmap → Add New**).
3. **See your public roadmap**: open the **Roadmap** page.
4. **Choose what visitors can do**: turn on comments, the *Suggest an idea* button, the toolbar or the extra views (**NiroRoadmap → Settings**).

Want to see a working board first? **Add example content** on the same screen fills it with example items, two products, tags and votes, and **Remove example content** takes only those away again.

## 🛠️ Showing the roadmap

Use the **Roadmap** block, or the shortcode in any page or post:

```
[niroroadmap]
```

| Attribute | Values | What it does |
| --- | --- | --- |
| `product` | a product ID | Show only that product's items. |
| `toolbar` | `yes`, `no` | Show the search, sort and filter toolbar. |
| `sort` | `manual`, `votes`, `newest`, `oldest`, `commented` | Initial sort. |
| `filters` | `search`, `tag`, `product` (comma list) or `none` | Which filters the toolbar offers. |
| `view` | `board`, `list`, `timeline` | The view the board opens in. |
| `switcher` | `yes`, `no` | Let visitors switch between views. |
| `group` | `quarter`, `month`, `nownext` | How the timeline groups items. |
| `submissions` | `yes`, `no` | Show the *Suggest an idea* button. |

Without an attribute, a board follows **NiroRoadmap → Settings**. Attributes combine:

```
[niroroadmap product="12" toolbar="yes" sort="votes" view="timeline" switcher="yes"]
```

The older `[roadmap]` shortcode still works.

## 🖥️ REST API

Namespace: `/wp-json/niroroadmap/v1`. Public routes are rate limited.

| Route | Method | Who | Purpose |
| --- | --- | --- | --- |
| `/tasks/{id}` | `GET` | Anyone | An item's details. |
| `/tasks/{id}/vote` | `POST` | Anyone* | Vote. Param: `type` (`upvote` or `downvote`). |
| `/tasks/{id}/comments` | `GET`, `POST` | Anyone* | List or add comments. Params: `page`; `content`, `parent`, `name`, `email`. |
| `/tasks/submit` | `POST` | Anyone* | Suggest an idea. Params: `title`, `description`, `product`, `name`, `email`. |
| `/tasks/search` | `GET` | Anyone | Find similar items. Param: `q`. |
| `/tasks/{id}/move` | `POST` | Editors | Move an item to another status. Param: `stage`. |
| `/tasks/order` | `POST` | Editors | Save the order of items. Param: `order` (array of item IDs). |
| `/stages/order` | `POST` | Editors | Save the order of statuses. Param: `order` (array of status IDs). |

\* Unless you require a login in **Settings**.

## 🧩 For developers

Roadmap data is a regular custom post type, `niroroadmap_item`, with the taxonomies `niroroadmap_status`, `niroroadmap_product` and `niroroadmap_tag`, so it works with the standard WordPress APIs.

- `niroroadmap_get_setting( $key )` returns a plugin setting. The `niroroadmap_setting_{$key}` filter has the last word on any of them.
- Filters include `niroroadmap_get_posts`, `niroroadmap_show_stage_links`, `niroroadmap_vote_fingerprint`, `niroroadmap_vote_rate_limit`, `niroroadmap_comment_rate_limit`, `niroroadmap_submission_verify` (add a CAPTCHA), `niroroadmap_submission_notify_to`, `niroroadmap_docs_url` and `niroroadmap_support_url`.
- Actions: `niroroadmap_comment_created` and `niroroadmap_submission_created`.
- Restyle the board by overriding `--nr-bg`, `--nr-card`, `--nr-border`, `--nr-text`, `--nr-muted`, `--nr-accent` and `--nr-radius` on `.nr-kanban-columns` and `.nr-modal-overlay`.

The full list, with what each one does, is in [readme.txt](readme.txt).

## 🤝 Contributing

Contributions are welcome. If you find a bug or have a feature request, [open an issue](https://github.com/codexpertio/niroroadmap/issues) or send a pull request.

```bash
composer install     # PHP dependencies
npm install          # block build tools
npm run build:blocks # build the Roadmap block
composer test        # integration tests, run against the WordPress install the plugin sits in
```

## 📜 License

NiroRoadmap is free software, licensed under the **GNU General Public License**. See [license.txt](license.txt).

## 💬 Support

Visit [support.nirosuite.com](https://support.nirosuite.com), email **<niro@nirosuite.com>**, or [open an issue on GitHub](https://github.com/codexpertio/niroroadmap/issues).

---

### 💡 Built by [NiroSuite](https://nirosuite.com)

🔗 **Website:** <https://nirosuite.com/niroroadmap>\
🔗 **GitHub:** <https://github.com/codexpertio/niroroadmap>

Also from NiroSuite: [NiroHelp](https://nirohelp.com) · [NiroCache](https://nirosuite.com/nirocache) · [NiroSitemap](https://nirosuite.com/nirositemap)
