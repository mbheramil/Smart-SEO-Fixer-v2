=== Smart SEO Fixer ===
Contributors: mbheramil
Tags: seo, ai, openai, meta description, schema, sitemap, search engine optimization, breadcrumbs, redirects, local seo
Requires at least: 5.8
Tested up to: 6.7
Stable tag: 2.0.84
Requires PHP: 7.4
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

AI-powered SEO optimization plugin that analyzes and fixes SEO issues using AWS Bedrock (Claude), OpenAI, Anthropic, or Google Gemini.

== Description ==

Smart SEO Fixer is a powerful WordPress plugin that uses AI to analyze and optimize your website's SEO. Choose your AI provider — AWS Bedrock (Claude, the default), OpenAI, Anthropic Claude, or Google Gemini. It automatically detects issues and generates optimized titles, meta descriptions, and alt text — with zero gaps.

**Key Features:**

* **SEO Analysis** - Comprehensive analysis of titles, meta descriptions, content, headings, images, and links
* **AI-Powered Generation** - Generate optimized SEO titles, meta descriptions, and focus keywords using AWS Bedrock (Claude), OpenAI, Anthropic, or Gemini
* **4-Layer SEO Protection** - Auto-generate on publish/update, background cron, dashboard alerts, and bulk fix
* **SEO Score** - Get a score from 0-100 for each post/page with detailed feedback
* **Readability Scoring** - Flesch Reading Ease, sentence length, passive voice detection
* **Bulk Analysis & Fix** - Analyze and AI-fix all posts at once
* **Schema Markup** - Automatic JSON-LD structured data for articles, pages, products, and local business
* **XML Sitemap** - Built-in sitemap generator with automatic search engine pinging
* **Meta Tags** - Full control over titles, descriptions, canonical URLs, and robots meta
* **Open Graph & Twitter Cards** - Automatic social media meta tags with image fallbacks
* **Social Previews** - Live Google, Facebook, and Twitter preview in the editor
* **Redirect Manager** - 301/302 redirects, auto-detect slug changes, 404 tracking
* **Breadcrumbs** - Schema-enriched breadcrumbs via shortcode or PHP function
* **Local SEO** - Multiple business locations with LocalBusiness schema
* **WooCommerce SEO** - Product schema, category SEO, brand/GTIN fields
* **Search Console Fixer** - Detect and fix trailing slash issues, redirect chains, canonical conflicts
* **Migration Tool** - Import from Yoast, Rank Math, AIOSEO, SEOPress, The SEO Framework
* **Auto-Updater** - GitHub-based updates with private repo support
* **Theme Compatibility** - Works with any theme, including those without title-tag support

**What Gets Analyzed:**

* Title length and keyword placement
* Meta description length and content
* Content length and keyword density
* Heading structure (H1, H2, H3)
* Image alt text
* Internal and external links
* URL/slug optimization
* Readability metrics

== Installation ==

1. Upload the plugin files to `/wp-content/plugins/smart-seo-fixer/`
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Go to **Smart SEO → Settings** and configure your AI provider (AWS Bedrock, OpenAI, Anthropic Claude, or Google Gemini)
4. Click **Analyze All Posts** on the dashboard to get started
5. Enable **Auto Meta Generation** in Settings for hands-free SEO

== Frequently Asked Questions ==

= Do I need an AI API key? =

Yes, to use the AI-powered features (title generation, meta description generation, keyword suggestions), you need credentials for one provider: AWS Bedrock (access key + secret), OpenAI, Anthropic Claude, or Google Gemini.

The plugin will still work for manual SEO analysis without an API key.

= What OpenAI model should I use? =

We recommend **GPT-4o Mini** for the best balance of quality and cost. It's fast, affordable, and produces excellent SEO content.

= How much does it cost to use? =

The plugin itself is free. OpenAI charges based on usage. GPT-4o Mini costs approximately $0.15 per million input tokens and $0.60 per million output tokens - very affordable for SEO content generation.

= Will this conflict with other SEO plugins? =

Smart SEO Fixer includes a migration tool to import data from Yoast, Rank Math, and others. You can also enable "Disable Other SEO Output" in settings to prevent duplicate meta tags.

= Is my content sent to OpenAI? =

When you use AI features, relevant content is sent to OpenAI for processing. OpenAI does not use API data for training.

= Does this work with any theme? =

Yes. The plugin forces title-tag support for themes that don't declare it, and includes a fallback output buffer to ensure titles always render correctly.

== Screenshots ==

1. Dashboard showing SEO health scores and statistics
2. SEO analysis metabox in post editor with social previews
3. Bulk analysis and AI fix of all posts
4. Search Console Fixer for URL consistency
5. Redirect Manager with 404 tracking
6. Settings page with API configuration

== Changelog ==
= 2.0.84 =
* Bedrock broker mode is now simpler to turn on: only SSF_BEDROCK_BROKER_TOKEN needs to be set in wp-config.php. The broker's URL ships as a built-in default — it's not sensitive on its own (a request without a valid per-site token gets nothing but a 401), only the token needs to stay out of the codebase. SSF_BEDROCK_BROKER_URL remains available to override the default if you ever point a site at a different broker deployment.

= 2.0.83 =
* NEW: AWS Bedrock broker mode. Set SSF_BEDROCK_BROKER_URL and SSF_BEDROCK_BROKER_TOKEN in wp-config.php to route Bedrock requests through a small proxy that holds the real AWS credential centrally (never on any WordPress site). Each site instead gets a cheap, disposable per-site token — worthless if copied via a migration/backup/clone, and instantly revocable without touching AWS. Fully backward compatible: without these constants defined, behavior is unchanged. Bulk/parallel AI runs fall back to sequential requests when broker mode is active (correct, just not the fast concurrent path).

= 2.0.82 =
* Internal: This release retargets the auto-updater to a new repository (mbheramil/Smart-SEO-Fixer-v2). No functional changes. Future updates will be checked and downloaded from the new repository going forward.

= 2.0.81 =
* Fix: Content Suggestions treated a WooCommerce product exactly like a blog post — a suggestion could read "change title from 'Wedding Cigar Station' to 'Cigar Station for Weddings & Events'", which looks like it's proposing to rename the product itself. It never actually touched the product name (title suggestions only ever update the separate SEO title tag), but the wording didn't say that. Suggestions for products (and any custom post type you register via the ssf_content_suggestions_named_entity_post_types filter) now say explicitly that the name/title stays unchanged, and the AI is instructed never to propose a rename for these post types in the first place.
* NEW: Suggestions that can't be generated automatically (missing image, missing internal/external link) now include a "View Live" link to the actual page, so there's a real next step instead of a dead-end error message.

= 2.0.80 =
* NEW: "Generate & Insert" button on every Content Suggestions card. Click it and AI writes the fix and saves it immediately — a new SEO title, a new meta description, or an added content section, depending on what the suggestion is about. Suggestions about a missing image or a missing link are left advisory-only, since fabricating a fake image or link target would do more harm than good.
* Fix: The content score circle only ever reflected the rules pass and went stale the moment AI suggestions were added below it — a page could show a healthy "85" while several AI-found high-priority issues sat unfactored beneath it. The score now recalculates from every suggestion actually on the page.
* Fix: The "Select a post to analyze" search gave no feedback for zero matches or a failed request — it just silently showed nothing, indistinguishable from a broken search box. It now shows "Searching…", "No posts found.", or the actual error.

= 2.0.79 =
* Fix: The "View Details" popup on the Plugins page no longer shows a clickable link on the author name or a "Plugin Homepage »" link in the sidebar.

= 2.0.78 =
* Fix: The "View Details" popup on the Plugins page (Plugins → Smart SEO Fixer → View details) showed "mbheramil" as the author, out of sync with the plugin header's "Author: mbh". Now shows "mbh" consistently.

= 2.0.77 =
* NEW: Address autocomplete on the Local SEO page. Start typing a street address and pick a suggestion to auto-fill city, state, ZIP, country, and coordinates — for both the main business address and additional locations. Manual typing still works exactly as before either way.
* Requires a Google Maps/Places API key set via the SSF_GOOGLE_MAPS_API_KEY constant in wp-config.php — not a Settings field. This key is billed per request, so it is never stored in the database or committed to this (public) repository; it lives only in wp-config.php on each site, the same way AWS Bedrock credentials can be set via SSF_BEDROCK_ACCESS_KEY. Without that constant defined, the address fields behave exactly as before.

= 2.0.76 =
* Local SEO: Saving now shows exactly what schema markup was generated, right on the settings page — a "Generated Schema" panel appears with the JSON-LD after every save, so you can confirm it worked without having to check your site's page source. If "Enable Local Business Schema" is off, the panel says so clearly.
* Local SEO: Added a proper error message if a save request fails over the network, instead of the button just re-enabling with no explanation.
* Fix: Local Business schema generation could throw PHP warnings (visible in debug logs, and potentially leaking into page output) if the stored settings were ever missing their "hours" or "social" keys.

= 2.0.75 =
* IMPORTANT FIX: Content Suggestions never worked — every request hit a PHP fatal error (the AJAX handler called a method that doesn't exist on the analyzer class) and the page was stuck on "Analyzing content..." forever with no error shown, because the request also had no failure handler to report it. Fixed the method call so analysis actually runs, and added an error message for network/server failures instead of an infinite spinner.

= 2.0.74 =
* Removed the two remaining links to Client Report — the row on the Settings page and the shortcut tile on the Dashboard. Client Report is no longer reachable from anywhere in the admin UI.

= 2.0.73 =
* Admin menu: Removed "Client Report" from the Smart SEO sidebar menu — it's linked from the bottom of Settings instead, so it isn't a separate nav item. The page itself still works exactly as before for anyone using that Settings link.

= 2.0.72 =
* Settings: Removed the GitHub Token field. It only mattered if this plugin's update repository were private, and it is public, so the field was unused. The last row on the Settings page now links to Client Report instead.
* Admin menu: Removed "Code Audit" and "Performance" from the Smart SEO menu, along with their Dashboard shortcuts.

= 2.0.71 =
* Cleanup: When AWS Bedrock credentials are set via wp-config.php constants, Settings no longer shows the masked Access Key ID / Secret Access Key / AWS Region fields (which were disabled and uneditable anyway). It now shows a simple Configured / Not configured status instead, with Test Connection still available.

= 2.0.70 =
* Fix: One-click GA4 property creation could fail with "The value for the 'time_zone' field must be a valid value from the IANA timezone database." This happened on sites where Settings > General > Timezone is set to a manual UTC offset (e.g. "UTC+2") rather than a city — WordPress returns that as a raw offset string, which Google Analytics rejects outright. Auto-setup now converts whole-hour manual offsets to the equivalent IANA zone and falls back to UTC for anything else, so property creation succeeds regardless of how the site's timezone is configured.

= 2.0.69 =
* IMPORTANT FIX: When the AI could not actually look at an image, the plugin used to ask it to describe the picture from the file address alone. A model that cannot see the image will happily invent what is in it, and that invented description was saved and reported as an AI description. Alt text that confidently describes the wrong thing is worse than none — it misleads screen-reader users and search engines. The plugin now refuses to guess: if the image cannot be seen, it falls back to the filename and tells you so.
* NEW: "Test AI Vision" button in Settings. It sends one image from your Media Library to your AI provider and shows you the description it came back with, so you can confirm the AI is really looking at your images. If it cannot, the test names the exact cause — missing credentials, a model that does not support images, or an unreadable image file — and what to do about it.
* NEW: Every alt text run now reports whether descriptions came from the AI or from filenames, and when filenames were used, why. Previously a run could report "Done! 12 updated" with no hint that all twelve were guesses from filenames.
* NEW: The Media Library Alt Text column marks any description that came from a filename with "(from filename)", so text that was never based on the actual image is easy to spot and redo later.
* Settings now warns you up front if AI vision is not working, instead of only discovering it after a run.
* Fix: A model that cannot analyse images was treated as "AI ready" purely because credentials were saved. Bulk runs were therefore throttled to tiny batches waiting on API calls that never happened, and new uploads were needlessly delayed by 15 seconds.
* Fix: Automatic alt text on upload runs in the background where nobody sees the result. It now records in the plugin log when it had to fall back to the filename, so "why is my new image's alt text just the file name?" is answerable.
* Applies to all four providers: AWS Bedrock, OpenAI, Anthropic Claude and Google Gemini.

= 2.0.68 =
* NEW: Alt text is now editable right in the Media Library. A new "Alt Text" column shows each image's current alt text, or a red "Missing" marker, with a Generate button on every row (Rewrite when text is already there). No need to open each image.
* NEW: "Generate with AI" button under the Alternative Text field, on both the attachment edit screen and the media popup you get when inserting an image into a post. The field is filled in place so you can review or edit before saving.
* NEW: "Generate alt text (Smart SEO)" bulk action in the Media Library — tick several images, pick it from the Bulk actions dropdown, and get a summary of how many were described, already had text, or could not be described. To protect your work it only fills images that are MISSING alt text; use Rewrite Existing Alt Text in Settings to replace text.
* Yes, new uploads are still described automatically — that behaviour is unchanged and controlled by "Automatically generate alt text for new image uploads" in Settings. The new buttons are for images already in your library, or when you want to redo one by hand.
* Fix: Loading plugin assets on the Media Library could have blanked the post editor's script configuration, because WordPress replaces (rather than merges) a repeated script localization. The Media Library now reuses the editor's configuration instead of declaring a second one.
* Per-image generation is permission-checked per attachment, so editors can describe their own uploads without needing full administrator rights.

= 2.0.67 =
* NEW: The Bulk Generate Alt Text panel now shows live counts — total images in the Media Library, how many have alt text, how many are missing it, and your coverage percentage. Previously there was no way to see the size of the job before starting it.
* NEW: "Rewrite Existing Alt Text" button. The old button only filled in images with an EMPTY alt field, so images already holding weak filename-based text (or text left over from the 2.0.66 naming bug) were invisible to it and the run reported "0 updated". The new button regenerates alt text that already exists.
* Alt text this plugin generated is now tagged as such, so rewriting can safely replace its own output while leaving alt text you wrote by hand untouched. An opt-in checkbox is available if you really do want to overwrite everything.
* Fix: Alt text on upload was generated twice by two different handlers hooked to the same event. The older synchronous one won the race and blocked the media uploader with an AI request, which meant the background processing added in 2.0.66 never actually took effect. There is now a single upload path.
* Fix: The older upload handler used its own copy of the filename cleaner that still contained the pre-2.0.66 bug and did not sanitise AI output. It now delegates to the single maintained implementation.
* Fix: Alt text counts now exclude images in the Trash, so the totals match what you see in the Media Library.
* Enhanced: Rewrite results show the old text struck through next to the new text, so you can see exactly what changed.
* Enhanced: Counts refresh automatically as a run progresses, plus a "Refresh counts" link.

= 2.0.66 =
* CRITICAL FIX: Image alt text was being corrupted. The filename cleaner stripped a leading letter from ordinary words, so "party-rentals.jpg" became "Arty Rentals", "photo-booth.jpg" became "Hoto Booth", "pool-slide.jpg" became "Ool Slide", and "video-tour.jpg" became "Eo Tour". Camera prefixes (IMG_, DSC_, PXL_) are still removed, but only when followed by a separator or digits — never when they are part of a real word.
* CRITICAL FIX: Corrupted alt text already saved to your site is repaired automatically on update. Only values that exactly match the old buggy output are touched; anything you wrote by hand is left alone.
* CRITICAL FIX: "Generate Missing Alt Text" could loop forever. Images with no usable filename (IMG_1234.jpg) were never excluded from the next batch, so the progress never completed and the browser kept hammering the server. Undescribable images are now flagged and skipped, with a "Retry skipped images" link.
* NEW: Bulk alt-text generation now uses AI vision — the image itself is sent to the model, which describes what is actually in the picture instead of guessing from the filename. Works with Bedrock (Claude), OpenAI (GPT-4o), Claude API, and Gemini.
* Fix: The parallel (fast) alt-text path used when fixing a post's images sent only the image URL as text, so the model never saw the image and invented descriptions. It now sends the actual image bytes.
* Fix: Alt text generated for images in post content is now also saved to the Media Library (attachment alt field), so themes, page builders, and the "missing alt text" counters pick it up.
* NEW: Gemini now supports vision for alt text (previously URL-only, which could not see the image).
* Enhanced: Alt-text generation on upload runs in the background, so uploading images is no longer slowed down by an AI request.
* Enhanced: Bulk generation shows a real progress bar, live sample results labelled AI or filename, a Stop button, and resumes where it left off if a request fails.
* Enhanced: AI output is cleaned up — wrapped quotes, "Alt text:" labels, and "Image of…" preambles are stripped, and long descriptions are trimmed to a screen-reader-friendly length.
* Fix: Settings no longer claim uploads are described by a vision AI when no AI provider is configured; it now tells you it is using filenames and how to change that.

= 2.0.65 =
* Fix: Existing broken-link false positives are now cleared automatically. Links that older versions flagged as broken but are really just bot-blocked, auth-protected, or rate-limited (403, 401, 429, and LinkedIn's 999 — e.g. YellowPages, LinkedIn, many directories) are removed from the list on update. New scans already ignore these (v2.0.64); this cleans up the ones already recorded.

= 2.0.64 =
* CRITICAL FIX: Broken Link Checker "Scan Now" did nothing on real sites. It tried to scan up to 50 posts (each link waiting up to 10s) in a single request, which always exceeded the PHP time limit — the request died with no feedback and the button spun forever. The scan now runs in small batches with a live progress bar, resumes if a batch times out, and updates the stat cards when done.
* Enhanced: Far fewer false positives. Link checks now use a browser-like user agent, retry with a ranged GET when a server rejects HEAD (403/405/400/501), and treat auth/bot-block/rate-limit responses (401, 403, 429, LinkedIn's 999) as working links rather than "broken."
* Enhanced: Broken-link stat cards refresh instantly after a scan or filter without a page reload.

= 2.0.63 =
* New: Refresh button on the 404 Error Log (Redirect Manager) to re-check entries on demand.
* New: The 404 log now shows a green "Redirected" badge on any entry that's already covered by an active redirect rule (exact or path-preserving wildcard), so you can instantly see which 404s are resolved vs. still need attention.

= 2.0.62 =
* New: Path-preserving wildcard redirects. When the "From" ends with * and the "To" ends with a slash (or *), the rest of the request path is carried across — so a single rule like /wp-content/uploads/* → https://cdn.example/wp-content/uploads/ forwards every file to the same path on a CDN, keeping the filename. Previously wildcard redirects sent everything to one fixed URL. Ideal for moving a folder or catching offloaded-media 404s (e.g. WP Offload Media / CloudFront) that a media player references by hardcoded URL.
* Fix: The redirect "From" field now also accepts a full URL (its path is used), so a pasted absolute URL matches correctly instead of silently never firing.

= 2.0.61 =
* Fix: A bogus "Some pages could not be fixed: Unknown fix type" popup appeared when running the orphaned-pages "Fix All with AI" bulk action. The orphan fix was actually working — a second (generic) click handler was double-firing on the same button and sending an unknown fix type. The generic handler now correctly ignores the orphaned-pages button, which has its own handler.

= 2.0.60 =
* New: IndexNow instant indexing. New and updated pages are pushed to Bing, Yandex, Seznam, Naver and other IndexNow engines the moment they publish — crawled in minutes instead of days. Enabled by default; a verification key file is served automatically. Replaces the old Google/Bing sitemap "ping" calls, which both engines retired in 2023.
* New: Image sitemap. The XML sitemap now lists each page's images (featured + in-content) so they can be discovered and rank in Google Images.
* New: Crawl & index hygiene. Internal search-results pages, 404s, and paginated archive subpages (page 2+) are now noindexed, so Google spends crawl budget on real pages and doesn't index thin/duplicate listings.
* Fix: Open Graph image dimensions now reflect the actual image instead of a hardcoded 1200×630, so social/SERP previews render correctly.
* Improved: The AI Content Suggestions analysis is now keyword-ranking-focused — it tells you the specific subtopics, searcher questions (People Also Ask style), related terms, internal links, and depth changes to add so a page can rank for its target keyword, grounded in the actual content (no invented facts).

= 2.0.59 =
* CRITICAL FIX: The Search Console "AI Generate" / "AI Generate All Missing" buttons showed "Fixing X/Y…" but left every row marked "Missing" with no explanation. Two causes, both fixed: (1) the bulk loop silently swallowed every failure, so a run where all AI calls failed looked identical to a successful one; (2) even successful fixes never updated the row text. Each row now shows ✓ Fixed (with the new title/description) or ✗ with the actual error, and the run ends with a clear "X fixed, Y failed" summary plus the real failure reason.
* Fix: Automatic AI model fallback. If Claude Haiku 4.5 isn't enabled for your AWS Bedrock account/region, the plugin now automatically falls back to Claude 3.5 Haiku so AI generation keeps working instead of failing silently — and logs a one-line notice in the Debug Log telling you to enable the newer model under AWS Console → Bedrock → Model access.

= 2.0.58 =
* Fix: Reduced AI hallucination in generated titles, meta descriptions, and focus keywords. Every generation prompt now carries an explicit grounding rule — the model may only use facts present in the post content and is forbidden from inventing statistics, prices, locations, dates, awards, ratings, or unfounded superlatives ("best", "#1", "top-rated", "leading"). Applies to the per-post buttons, Bulk AI Fix, and the background cron, across both AWS Bedrock and OpenAI.
* Fix: Lowered the generation temperature to 0.3 for titles/descriptions/keywords (was 0.5–0.7), keeping output much closer to the actual content.

= 2.0.57 =
* CRITICAL FIX: The XML sitemap was being served with an HTTP 404 status on many sites — valid XML, but Google rejects any sitemap that doesn't return HTTP 200, so nothing got indexed from it. The sitemap now always responds 200 OK.
* Fix: Sitemap stylesheet declared XSLT 2.0, which no browser can render — viewing /sitemap.xml showed a blank or raw page. Corrected to XSLT 1.0.
* Fix: Sitemap rewrite rules are now re-flushed automatically on update, so /sitemap.xml and all sub-sitemaps resolve without a manual Settings → Permalinks save.
* Fix: Post-type sub-sitemaps are no longer listed in the index when all their published posts are noindex (which produced empty sitemaps Google flags as errors).
* Change: AI model upgraded to Claude Haiku 4.5 on AWS Bedrock (us.anthropic.claude-haiku-4-5-20251001-v1:0) — newer, sharper SEO copy at the same low cost. Existing sites are migrated automatically.

= 2.0.56 =
* Fix: Migration engine rewritten — Rank Math template variables (%title%, %sitename%, %sep%) are now resolved instead of being imported as literal text, and Rank Math's robots array no longer crashes the import on PHP 8.
* Fix: All in One SEO v4 data is now read from its custom database table (titles, descriptions, focus keyphrase, canonical, robots, social) — previously v4 sites migrated almost nothing.
* Feature: Migration now imports social media data (Open Graph + Twitter titles, descriptions, images), nofollow flags, and AIOSEO v4 smart tags (#post_title etc.).
* Feature: SEOPress and The SEO Framework migration support (previously claimed but not implemented).
* Fix: Drafts, scheduled, pending, and private posts are now migrated (previously silently skipped — published-only).
* Fix: Posts with only a focus keyword, canonical, or robots flag (no title/description) are now picked up by migration.
* Fix: Migration errors now surface as readable messages in the progress UI instead of breaking the progress loop.
* UI: Migration page now states exactly what is and isn't migrated; preview shows resolved titles exactly as they will be saved.

= 2.0.55 =
* UI: Complete admin design-system refresh — modern layered shadows, refined color palette, larger radii, gradient primary buttons, polished inputs with focus rings, table row hover states, filter chips, animated progress bars with shimmer, modal backdrop blur with spring entrance, staggered stat-card animations, styled scrollbars, and page fade-in.
* UI: Visible keyboard-focus outlines for accessibility; all motion respects prefers-reduced-motion.
* UI: WordPress core buttons, inputs, selects, and list tables restyled consistently across all plugin screens (scoped to plugin pages only — the post editor and the rest of wp-admin are untouched).

= 2.0.54 =
* Fix: Redirects created from the 404 Monitor never actually fired (wrong flag key, no ID) — now routed through the Redirect Manager, and existing broken rules are auto-repaired via DB migration v8.
* Fix: Background job queue is now protected by a cross-request lock — concurrent cron/loopback/poll workers could previously process the same batch twice (duplicate AI calls, corrupted progress counts).
* Fix: Stuck-job detection now tracks real progress — large bulk jobs running longer than 30 minutes were being killed mid-run even while still advancing.
* Fix: Consolidated the two parallel 404 logging systems into the DB-backed 404 Monitor; the Redirects page tab and Search Console scan now read from it.
* Fix: 404 log growth is now capped (least useful entries pruned automatically) and stale job/404 cleanup routines actually run.
* Fix: Slug-change auto-redirects now compute the old URL safely (previously corrupted URLs when the slug appeared elsewhere in the path).
* Security: Plugin deletion now removes ALL stored credentials (OpenAI/Claude/Gemini API keys were previously left in the database) plus every option, transient, and cron event.
* Security: GitHub updater token is now only sent to verified github.com hosts (was vulnerable to substring-match spoofing).
* Performance: Table-existence checks run once per plugin version instead of on every admin page load.
* Reliability: Queue loopback tick no longer rejected at hour boundaries.

= 2.0.53 =
* Chore: Version bump.

= 2.0.52 =
* Fix: Version bump to trigger auto-update on sites still running older versions.

= 2.0.51 =
* Feature: Instant auto-update — plugin checks GitHub every 5 minutes via WP-Cron and silently applies new versions without requiring a manual click.

= 2.0.50 =
* Chore: Remove Plugin URI and Description from plugin header to hide author/repo info on WP Plugins page.

= 2.0.49 =
* Fix: Haiku 3.5 on Bedrock also requires the `us.` cross-region inference profile prefix — on-demand direct invocation is not supported. Correct model ID: `us.anthropic.claude-3-5-haiku-20241022-v1:0`.

= 2.0.48 =
* Fix: Haiku model ID still invalid — segment order was wrong (`claude-haiku-3-5` → `claude-3-5-haiku`). Correct Bedrock ID is `anthropic.claude-3-5-haiku-20241022-v1:0`.

= 2.0.47 =
* Fix: "The provided model identifier is invalid" error on Bedrock after Haiku switch. Claude 3.x models use the direct Bedrock model ID (`anthropic.claude-haiku-3-5-20241022-v1:0`) without the `us.` cross-region prefix — that prefix is only for Claude 4.x inference profiles.

= 2.0.46 =
* Fix: Orphaned page "Add Link with AI" always failed with "Could not find a natural placement" for location/service-area pages (e.g. "Inspections in Webster Groves, MO"). Root cause: no other page on the site mentions the city name, so AI correctly returns `{found:false}` for all candidates — and the fallback page also fails. Added a guaranteed last-resort: when all AI placement attempts fail, the plugin now appends a `Related: [page title]` link paragraph to the highest-relevance candidate page, ensuring every orphan gets at least one incoming internal link.

= 2.0.45 =
* Change: Switched AWS Bedrock model from Claude Sonnet 4.6 to Claude 3.5 Haiku (`us.anthropic.claude-haiku-3-5-20241022-v1:0`) — same quality for SEO tasks at 4x lower cost.

= 2.0.44 =
* Fixed: Image alt-text generation was producing hallucinated / generic output because every provider (Bedrock, Claude, OpenAI) was sending only the image URL as plain text — the AI could not actually see the image, it was just guessing from the filename slug. Now uses true vision: the image bytes are fetched, base64-encoded, and sent as a multimodal message block. Claude on Bedrock (vision-capable), Anthropic Claude, and OpenAI GPT-4o/4-turbo all receive the actual pixels and describe what they see. Images larger than 4 MB are auto-resized to Claude's recommended max 1568px edge. Non-vision models (Llama / Mistral / Titan on Bedrock) fall back to the URL-only prompt.
* Added: `SSF_AI::fetch_image_as_base64()` helper that reads images from local uploads (fast path) or falls back to wp_remote_get, sniffs media type, and rejects unsupported formats (accepts jpeg/png/gif/webp only).

= 2.0.43 =
* Fixed: Bulk AI Fix silently capped at ~999 posts per run no matter how many the user selected. Root cause: the frontend sent `post_ids[]` as an array, which on large selections exceeds PHP's default `max_input_vars = 1000` and gets silently truncated by PHP before WordPress ever sees it. With 1453 selected, only the first 999 (ordered by post_date DESC) reached the server — and those were the same 999 that prior runs had already filled in, so every run returned "0 generated · 999 skipped — already has SEO data". Frontend now sends the selection as a single CSV field (`post_ids_csv`), so the entire list reaches the server in one input var regardless of size. Applied the same defense to `bulk_fix` and `bulk_analyze` endpoints.

= 2.0.42 =
* Fixed: Applied the v2.0.41 enriched-context helper (post_content + post_excerpt + public post_meta + image alt/caption) to EVERY remaining AI generation entry point, not just Bulk AI Fix. Previously these paths still read raw `$post->post_content` and silently skipped page-builder / location-template CPTs the same way Bulk AI Fix did: post-save cron auto-SEO (`smart-seo-fixer.php` + `class-admin.php`), per-post meta-box Generate buttons for title / description / keywords / analyze, Fix Issue (title & description), bulk_fix sequential + parallel, bulk_ai_fix in-request + sequential fallback, ai_fix_single, Search Console duplicate-regen, fix_missing_seo_data, not-indexed AI fix (keyword + title + description), generate_unique_title, generate_unique_desc, content suggestions generator, and the job queue `process_not_indexed_fix` sequential path. All of these now pass the same enriched string used by the parallel Bulk AI Fix pipeline, so page-builder and location CPTs produce real SEO output across every UI surface.

= 2.0.41 =
* Fixed: Bulk AI Fix reported "999/999 completed" but post_meta wasn't actually written for page-builder / location-template post types. Root cause: post_content on those CPTs is empty (real content lives in post_excerpt, ACF/page-builder meta, or attached image alt text), so the word-count gate skipped every post silently as "content too short" and counted the skip as success. Both the parallel and sequential bulk paths now call a new `enrich_post_context()` helper that combines body + excerpt + public meta + image alt/caption before the word-count gate and before prompting the AI.
* New: Completion screen now shows a real outcome breakdown — "N generated · M skipped · K failed" with per-reason skip counts. If every post was skipped, a warning is surfaced so the user knows the job was a no-op instead of quietly assuming success.
* Changed: `ssf_get_job` response now includes a `summary` object with generated / skipped / failed / reasons.

= 2.0.40 =
* Fixed: Job Queue page always said "OpenAI Rate Limit" even when the site was wired to AWS Bedrock, Claude, or Gemini. The card now reflects the active AI provider's label and reads that provider's actual rate-limiter bucket. No behavior change — the bulk pipeline was already using the correctly configured provider; only the label was wrong.
* Fixed: Job Queue page description said "10+ posts, 5 items per minute" which no longer matched the current parallel pipeline. Updated to "5+ posts, batches of 20 in parallel on Bedrock".

= 2.0.39 =
* Fixed: Job Queue page "Recent Jobs" was always empty even when jobs existed in the database. The `ssf_get_jobs` response returned `items` but the view expected `jobs`, so the table never rendered. Now returns both keys and also computes the `progress` percentage per row.
* Fixed: Bulk AI Fix progress bar stuck at 0/N. The `ssf_get_job` polling endpoint was reading non-existent columns (`processed_count`/`failed_count` instead of `processed_items`/`failed_items`), so it always reported zero progress even while batches were actually being processed.
* Fixed: Non-Bedrock bulk jobs (OpenAI, Claude, Gemini) stalled at 0/N on low-traffic sites because the loopback self-tick only re-fired for Bedrock-parallel jobs. Every job type now chains its own next batch, turning long bulk runs from "1 post per minute via WP-Cron" into "continuous processing".
* Fixed: Added a belt-and-braces self-kick inside the progress polling endpoint — if the job is pending/processing, each UI poll nudges the queue forward. On hosts where `wp_remote_post` non-blocking loopbacks get swallowed by caching/reverse-proxy layers, the browser itself now keeps the pipeline moving.

= 2.0.38 =
* Fixed: Bulk AI Fix now routes batches of 5+ posts through the Job Queue so they are visible on the Background Jobs page (previously the client-side loop never triggered queuing because it fragmented into batches of 5).
* Fixed: Bulk AI Fix is dramatically faster. Instead of the browser firing 299 sequential HTTP requests of 5 posts each (each request doing 10 sequential AI calls), the entire selection is now sent once and processed server-side in parallel batches of 20 with curl_multi. For 1,494 posts this drops processing from hours to minutes on Bedrock.
* New: Live progress bar on the Bulk AI Fix page polls the Job Queue every 2 seconds and shows processed/total count + status. Progress stalls automatically trigger a queue self-tick so you don't have to wait for WordPress cron.
* New: `ssf_get_job` AJAX endpoint returns a single job's progress (id, status, total, processed, percent, failed, error) for live UI polling by third-party integrations.
* In-request fast path: batches of <5 posts still run synchronously and now use the same parallel curl_multi Bedrock call for instant results.

= 2.0.37 =
* New: Thin-content auto-noindex. Posts below the word threshold (default 50 words) are automatically marked noindex so Google won't count them against your site's SEO. Applies to image-only posts and super-short "thank you" style reviews. If a post grows above the threshold later, the plugin lifts the noindex automatically. Fully configurable in Settings → General → Thin Content Auto-Noindex.
* New: Image-only SEO enrichment. When a post is mostly images (e.g. a client-review gallery), the plugin now feeds every image's alt text, caption, title, and description to the AI — so it can still generate a relevant SEO title, meta description, and focus keyword instead of skipping the post.
* New: Thin-content warning in the SSF meta box. You now see a clear message in the post editor if your content is below the threshold, with one-click guidance to either expand the content or leave it noindexed.
* Improved: Reports & Search Console reconciliation now exclude noindex posts from the "missing title / missing description / missing keyword" counts — you won't be nagged about posts that are intentionally hidden from search.
* Improved: The Analyzer short-circuits noindex posts with a neutral "excluded from search" result so they don't drag down your site's average SEO score.
* New helpers (for extensions/integrations):
  * `SSF_Validator::get_content_word_count($post)` — real word count after stripping shortcodes/tags/captions.
  * `SSF_Validator::is_thin_content($post, $threshold = 50)`
  * `SSF_Validator::extract_image_seo_context($post)` — pulls all image alt/caption/title text from a post for AI input.
* Meta keys added per post: `_ssf_auto_noindex` (1 if plugin set the noindex), `_ssf_content_word_count`, `_ssf_thin_evaluated` (timestamp).

= 2.0.36 =
* New: Auto-generate SEO title + meta description on first publish is now enabled by default. When you publish a new post/page, a background job runs ~5 seconds later and fills in any missing title, description, and focus keyword using the Bedrock SEO bundle (one parallel call).
* New: Hard character limits enforced everywhere. SEO title is truncated to 60 characters and meta description to 160 characters on every save path (auto publish, bulk AI fix, single-post generate, bulk fix, manual save, not-indexed fix) and on frontend output as a safety net for legacy data. Truncation happens on a word boundary so titles never cut mid-word.
* New helpers: SSF_Validator::enforce_seo_title(\$title, \$max=60) and SSF_Validator::enforce_meta_description(\$desc, \$max=160) for any third-party integrations to reuse.
* Performance: The publish-time auto-generation is now asynchronous (wp_schedule_single_event ~5s) so it never slows down saving or publishing.

= 2.0.35 =
* Fix: "Insert Internal Links" button in the post editor now works on unsaved content. The meta-box JS sends the live editor content to the server so the AI can find anchor phrases that aren't saved to the database yet. Previously it failed on new or freshly-edited posts because the server was reading stale DB content.
* Fix: Internal-link candidate search now uses the same broader word-overlap scoring as the Indexability "Orphan Fix" (trying 6 candidates instead of WP's narrow ?s= search against 10), so it finds related posts even when focus keyword is empty.
* New: Automatic internal linking on first publish. When a new post/page is published for the first time, a background job runs ~30s later to add up to 3 outgoing links from the new post to related posts, and up to 3 incoming links from related posts back to the new post. Uses parallel Bedrock when available. Setting: Settings → General → "Auto Internal Links" (default: on).
* Performance: Meta-box internal-link suggestions now run in parallel on Bedrock via curl_multi (up to 6 AI anchor searches fire concurrently instead of sequentially).

= 2.0.34 =
* Extended parallel Bedrock processing to three more AI flows:
  - Search Console "Fix All Not-Indexed" background job (20 posts concurrent per batch).
  - Synchronous Bulk Fix AJAX (title + description calls for all selected posts fire concurrently — was 2 sequential calls per post, now one parallel burst).
  - Image alt-text repair (all missing-alt images on a post generated concurrently instead of one by one).
* New public message-builder helpers on SSF_Bedrock: build_title_messages, build_desc_messages, build_alt_messages — enable reusing the same prompts with request_multi across the codebase.
* Non-Bedrock providers (OpenAI, Claude direct, Gemini) continue using the sequential path, so this change is backward-compatible.

= 2.0.33 =
* New: Parallel Bedrock AI processing — bulk AI fix now fires 20 posts concurrently via curl_multi instead of sequentially
* New: Combined "SEO bundle" prompt — one Bedrock call returns keyword + title + description as JSON (was 3 separate calls per post)
* New: Loopback self-ticking — bulk jobs no longer wait for WP-Cron's 1-minute tick between batches; each batch immediately triggers the next via a non-blocking HTTP request to admin-ajax.php
* Result: Bulk AI Fix for 1000+ page sites now completes in minutes instead of hours when using AWS Bedrock
* The parallel path includes a grounded-keyword fallback — if the AI returns a keyword that isn't in the post, the n-gram extractor takes over so we never save orphan keywords
* Non-Bedrock providers (OpenAI, Claude direct, Gemini) continue to use the sequential path, no behavior change

= 2.0.32 =
* Fixed: AI-generated focus keywords were often invented phrases that didn't appear in the post, causing the analyzer to deduct points for "keyword not found in title/content" even when meta coverage was 100%
* New: `SSF_AI::pick_grounded_keyword()` — AI suggestions are now validated against the actual post text; if no suggested keyword appears verbatim, a frequency-based n-gram is extracted from the title + content as a fallback
* New: AI keyword prompts now explicitly tell the model "every keyword MUST appear verbatim in the content"
* New: "Re-analyze All Pages" button on the Client Report page — batches through every published page and refreshes scores, so your Generated report reflects the latest state

= 2.0.31 =
* New: Client Report "Download PDF" now generates a real PDF file (using bundled html2pdf.js) instead of opening the browser's print dialog, so the exported file no longer contains the admin URL, page numbers, or the date/title header bar
* New: PDF files are auto-named `seo-report-<site>-<YYYY-MM-DD>.pdf`
* Improved: Removed the "Report generated by Smart SEO Fixer" footer from the Client Report
* Improved: PDF export button shows a spinner while rendering

= 2.0.30 =
* Improved: When a Google API needs to be enabled in your Cloud project, the plugin now shows a friendly banner with a clickable "Enable API" button that deep-links to the exact enablement page (with your project ID pre-filled) instead of showing raw error text
* Applied to: GA4 Use Existing Property picker, Auto-Create GA4 Property, Test Data Fetch
* Raw Google error is still available in a collapsible "Raw error from Google" block

= 2.0.29 =
* New: "Use Existing Property" button in Google Analytics settings — attach to a GA4 property you don't own
* New: Property picker dropdown lists every GA4 property the connected Google account has access to, grouped by account
* New: Optional "Also install tracking code" checkbox when selecting an existing property (auto-fills the Measurement ID from the chosen web stream)
* Use case: client's GA4 is in their Google account, they grant you Viewer/Analyst access, you pick their property here — reports work, optional tracking code installs

= 2.0.28 =
* New: Google Analytics 4 integration — connect GA4 with OAuth, auto-create a new property + web data stream with one click, and install the gtag.js tracking code automatically
* New: "Auto-Create GA4 Property for This Site" button in Settings
* New: Manual Measurement ID field for users who already have a GA4 property
* New: Website Traffic section in Client Report — shows sessions, users, pageviews, bounce rate, engagement rate, avg session duration, top landing pages, and traffic sources from GA4
* New: Test Data Fetch button in Settings to verify GA4 connectivity
* Note: Requires enabling the Google Analytics Admin API and Data API in your Google Cloud project

= 2.0.27 =
* New: One-click Search Console auto-setup — creates property, verifies ownership via meta tag, and submits sitemap automatically
* New: "Auto-Create Property for This Site" button in Settings after connecting Google
* New: Integrated Google Site Verification API (requires siteverification OAuth scope — existing users must disconnect and reconnect)
* New: Self-check step confirms the verification meta tag is actually served before asking Google to verify (catches cache issues early)
* Fix: Client Report accuracy — broken links "fixed" count now uses resolved log instead of dismissed flag
* Fix: Client Report date range now applies consistently across all sections
* Fix: Image SEO stats now scoped to images used in published content (no longer diluted by orphan media library uploads)
* Fix: Image detection now catches Gutenberg image blocks, not just raw <img> tags
* New: Data freshness indicator on Client Report showing score quality (good/partial/stale/none)

= 2.0.26 =
* Fix: Sub-sitemaps (sitemap-post.xml, sitemap-page.xml, etc.) returning blog page instead of XML
* Fix: Rewrite rules were registered too late (init priority bug) - now applied correctly
* Fix: Added fallback URL re-parsing in sitemap renderer when query var is missing or stuck as 'dynamic'
* Fix: flush_rewrite_rules() now called on plugin activation so sitemap URLs route correctly from day one

= 2.0.25 =
* New: AI Fix button on each score factor row — fixes the issue across all affected pages with one click
* New: Auto-detects fix type based on issue category (Title, Description, Keywords, or all)
* New: Progress modal with live log showing each page being fixed

= 2.0.24 =
* New: Click any issue in "Why Your Score Is What It Is" to open the affected pages in a new tab
* New: Posts page now supports filtering by specific SEO issue text
* New: Issue filter notice banner with clear button on Posts page

= 2.0.23 =
* New: "Why Your Score Is What It Is" section — shows top 10 most common SEO issues across all pages with frequency bars
* New: Each issue shows category (Content, Title, Description, etc.), the specific problem, and how many pages are affected
* New: Score Factors checkbox in report config panel

= 2.0.22 =
* Fix: Image alt text count was wrong — was scanning raw post HTML instead of checking attachment metadata (_wp_attachment_image_alt)
* Fix: Report showed >100% analyzed (e.g. 113%) because scores table included deleted/trashed/drafted posts
* Fix: All report queries (overview, score distribution, top pages, worst pages, issues) now filter by published posts in active post types only
* Fix: Analyzed percentage capped at 100%
* Fix: Issues section low-score and not-analyzed counts now accurate

= 2.0.21 =
* New: Auto-generate alt text from filename on image upload (enable in Settings > Auto Alt Text)
* New: Bulk Generate Missing Alt Text button in Settings — processes all existing images missing alt text
* Improved: Alt text generated from filenames (strips extensions, separators, size suffixes, capitalizes words)

= 2.0.20 =
* Improved: Template now replaces the cover section instead of just prepending above it
* Improved: Template CSS is scoped to the template banner area to avoid style conflicts
* Fix: Google Doc template styles were not applying because CSS selectors didn't match report HTML

= 2.0.19 =
* Fix: Fetched template was not applied to the generated report — now injects template styles and body content into the report

= 2.0.18 =
* Fix: Template fetch/clear "Security check failed" — was using wrong nonce and AJAX URL references

= 2.0.17 =
* Fix: Template fetch error showing [object Object] instead of actual error message

= 2.0.16 =
* New: Report Mode toggle — choose between Positive Only or Full Report (includes issues, negatives, recommendations)
* New: Template URL — paste a Google Doc or any URL to use its HTML/CSS as the report template
* New: Worst Pages section in full mode — bottom 20 pages by score with per-page issue tags
* New: Issues & Recommendations section in full mode — aggregated problems sorted by severity
* Improved: Full mode shows missing meta counts, unfixed broken links, needs-work scores, and unanalyzed pages
* Improved: Score distribution includes "Needs Work" bucket in full mode

= 2.0.15 =
* Improved: Client Report — comprehensive rewrite for much more useful, impressive reports
* New: Meta Tag Coverage section with progress bars (SEO titles, descriptions, focus keywords)
* New: Content Health section (avg word count, total words, readability score, images/links per page)
* New: Image SEO section (total images, alt text coverage percentage)
* New: Sitemap Status section (indexable pages, content types, sitemap URL)
* Fix: Top Pages table was empty due to duplicate score entries — now uses latest score per post
* Improved: Sections with zero/empty data are automatically hidden (true positive-only filtering)
* Improved: Score ring now shows grade badge (A/B/C+/C/D) with label (Excellent/Good/Fair/etc)
* Improved: Overview shows healthy-page percentage and analyzed-content percentage
* Improved: Score distribution bars show percentages alongside counts
* Improved: Schema section shows auto-coverage note explaining automatic structured data
* Improved: Optimizations section shows breakdown by type (titles, descriptions, keywords, schema, social)
* Improved: Keywords section shows total clicks and impressions
* Improved: Positive contextual notes throughout the report
* Improved: Print CSS with @page margin and color-adjust for new elements

= 2.0.14 =
* New: Client SEO Report — generate positive-only SEO reports for clients with animated score ring, score distribution, top pages, schema coverage, redirects, keyword rankings, broken links fixed, and optimizations performed
* New: Configurable date range (30/60/90 days, all time, or custom) and section toggles
* New: Print-friendly and PDF-ready output with clean styling (hides all WP admin chrome)
* New: Admin-only access (manage_options capability required)
* New: Dashboard nav card for quick access to Client Report

= 2.0.13 =
* Fix: Yoast meta description still duplicating after v2.0.12 — remove_action during init fires before Yoast registers its wp_head hook so the removal was silently ignored; now removes wpseo_head inside a wp_head priority-0 callback, which runs after Yoast has registered but before it fires

= 2.0.12 =
* Fix: Duplicate title and meta description tags — two bugs caused this: (1) SSF's remove_action for WordPress's built-in title tag was registered on after_setup_theme but SSF initialises on init (after_setup_theme fires first), so the removal never happened; fixed by calling remove_action directly in the constructor; (2) Yoast SEO hooks its entire head output via a standalone wpseo_head() function at wp_head priority 1 which SSF was not removing; added remove_action('wp_head', 'wpseo_head', 1) as the primary Yoast suppression

= 2.0.11 =
* Fix: SSF now falls back to Yoast SEO, Rank Math, All in One SEO, and SEOPress meta fields when SSF's own fields are empty — pages with existing SEO data from other plugins are never left without meta tags
* Fix: "Disable Other SEO Plugins Output" setting now shows clear warnings and Migration page links in all states (active plugin, plugin deactivated but data exists, checkbox enabled)

= 2.0.10 =
* Fix: Sitemap XSL stylesheet URLs now use query parameters (``/?ssf_sitemap=xsl``) instead of ``/ssf-sitemap.xsl`` paths — eliminates dependency on rewrite rules and .htaccess, making the styled sitemap work on all server configurations without needing a Permalink flush

= 2.0.9 =
* Fix: Sitemap XML now displays styled in all browsers — XSL stylesheets were previously intercepted by the webserver before reaching WordPress; now routed through the same WordPress request pipeline as the sitemap XML itself
* Fix: Added rewrite rules for XSL stylesheet URLs so they resolve correctly with any server configuration

= 2.0.8 =
* Enhancement: XML Sitemap now displays with a styled, readable layout in browsers (like Yoast) instead of raw XML
* Enhancement: Sitemap index shows sitemap count badge and "Last Modified" column
* Enhancement: Sub-sitemaps show URL count badge, priority, frequency, and last modified date in a clean table
* Enhancement: "Back to Sitemap Index" link on sub-sitemaps for easy navigation

= 2.0.7 =
* Fix: Auto-updater now uses direct GitHub archive URL instead of API zipball — fixes cURL error 6 (DNS resolution failure) on some servers
* Fix: Increased download timeout to 60 seconds and redirect limit to 10 for more reliable updates

= 2.0.6 =
* Enhancement: Sitemap now automatically includes ALL public post types (services, locations, products, FAQs, etc.) — not just posts and pages
* Enhancement: Sitemap now automatically includes ALL public taxonomies (custom categories, tags, etc.)
* Enhancement: Large sitemaps are automatically paginated (2000 URLs per file) to handle sites with thousands of pages
* Enhancement: Sitemap index only lists sub-sitemaps that actually contain content (no empty sitemaps)

= 2.0.5 =
* Fix: "Pages Not Appearing in Search" scanner now uses correct meta keys (_ssf_seo_title, _ssf_meta_description) — AI fix results now properly persist across page refreshes
* Fix: AI Fix button now works without clicking Inspect first — issue name mismatch (missing_meta vs missing_description) caused fix queue to be empty
* Fix: XML Sitemap now takes priority over Yoast SEO, Rank Math, AIOSEO, and WordPress core sitemaps when SSF sitemap is enabled
* Enhancement: Google inspection verdicts now show friendly labels ("Not Indexed" instead of "NEUTRAL") with helpful explanations
* Enhancement: Added missing issue labels for Noindex, No Internal Links tags in the not-indexed scanner

= 2.0.4 =
* Fix: Bulk 404 redirect reverted to reliable inline sequential processing (background job caused stalling)
* Fix: Schema bulk regenerate reverted to inline batch processing for instant feedback
* Fix: poll_job endpoint now actively drives job processing instead of relying solely on WP Cron
* Enhancement: Background jobs reserved only for AI-heavy operations (Bulk AI Fix on Search Performance)
* Enhancement: Job Queue page now shows clearer empty state with explanation of when jobs appear
* Enhancement: Added missing type labels for Not-Indexed AI Fix and Bulk 404 Redirect jobs

= 2.0.3 =
* New: Bulk AI Fix button for "Pages Not Appearing in Search" — select pages and fix all missing titles/descriptions at once
* New: All bulk operations (AI fix, schema regenerate, 404 redirects) now run as background jobs — you can leave the page while processing continues
* New: Generic job dispatch and polling system (ssf_dispatch_job / ssf_poll_job AJAX endpoints)
* New: Background job types: not_indexed_ai_fix, bulk_404_redirect
* Enhancement: "Select All" on 404 log and Pages Not Indexed now selects ALL items, not just the visible page
* Enhancement: Schema bulk regenerate now dispatches a background job with progress polling
* Enhancement: 404 bulk redirect now dispatches a background job instead of sequential inline AJAX
= 2.0.0 =
* New: Core Web Vitals (LCP, CLS, INP) real-user monitoring with p75 grading
* New: Image SEO — automatic lazy loading, eager first image, missing dimensions, decoding=async
* New: Weekly email digest with SEO score summary and action items
* New: Content duplication detection for titles and meta descriptions
* New: Internal link auto-insertion via AI-powered anchor matching
* New: Onboarding checklist with 7 milestone tracker
* New: Bulk fix preview with per-item approve/reject
* Enhancement: Bedrock API retry logic with exponential backoff (3 retries)
* Enhancement: Broken link scanner concurrency limit (5 parallel checks)
* Enhancement: Job queue dead-letter handling for stuck jobs with admin notification
* Enhancement: Canonical conflict auto-fix for duplicate titles, descriptions, and missing SEO data

= 2.0.2 =
* Enhancement: Bulk 404 redirect now shows a progress bar, counter (X / total), percentage, and completion status

= 2.0.1 =
* Fix: Added missing AJAX handlers for 404 Monitor (get, dismiss, redirect, clear)
* Fix: Added missing AJAX handler for Search Performance "Pages Not Indexed" scanner
* Fix: Added missing AJAX handlers for Keyword Tracker (get keywords, history, fetch now)
* Fix: Added missing AJAX handlers for Debug Log (get logs, clear logs)
* Fix: Added missing AJAX handlers for Change History, Job Queue, Social Preview, Readability, Robots Editor, Content Suggestions, WP Standards, and Performance
* New: Bulk redirect option in Redirect Manager 404 Error Log (select multiple 404s and redirect them at once)

= 1.16.15 =
* UI: Removed padlock label text from credential fields when constants are active
* UI: Removed "Before you start" info box from Bedrock settings

= 1.16.14 =
* Enhancement: AWS credentials can now be defined as PHP constants in wp-config.php (`SSF_BEDROCK_ACCESS_KEY`, `SSF_BEDROCK_SECRET_KEY`, `SSF_BEDROCK_REGION`) for improved security — constants take priority over database values
* Enhancement: Settings page shows a wp-config.php code snippet when constants are not yet set; locks credential fields with a padlock icon when constants are active
* Enhancement: Test Connection uses constants directly when defined, skipping any database credential handling

= 1.16.13 =
* Change: Model selection removed from settings UI — plugin now hardcodes `us.anthropic.claude-sonnet-4-6` (AWS CLI verified working)
* Fix: DB migration v7 now unconditionally sets the correct model ID in the database
* Fix: Setup wizard model dropdown removed; model is fixed in code

= 1.16.12 =
* Fix: Claude 4.x models now use cross-region inference profile IDs (`us.` prefix) — the catalog Model ID `anthropic.claude-sonnet-4-6` is not directly invokable; the invoke API requires `us.anthropic.claude-sonnet-4-6`
* Fix: DB migration v7 updated to fix bare catalog IDs saved without `us.` prefix → correct profile IDs
* Fix: `get_model_family()` now correctly detects `us.anthropic.claude-*` as Claude family

= 1.16.11 =
* Fix: DB migration (v7) automatically corrects any stale Bedrock model ID saved in the database with the old wrong format
* Fix: Custom model ID input now correctly passed to Test Connection and saved to DB
* Fix: Show/hide custom model input works on page load and on dropdown change

= 1.16.10 =
* Fix: Correct AWS Bedrock model ID for Claude Sonnet 4.6 — now uses `anthropic.claude-sonnet-4-6` (removed wrong date suffix and `us.` prefix)
* Fix: All Claude 4.x model IDs in dropdown updated to simplified format (e.g. `anthropic.claude-sonnet-4-6`)
* New: Custom model ID input field — select "Custom model ID" to paste any model ID directly from the Bedrock Model Catalog

= 1.16.9 =
* Fix: Test Connection error guidance now explains Anthropic use case approval requirement (required for first-time Claude model users via AWS)
* Fix: Info box in Bedrock settings now prominently shows the use case approval step before credentials setup

= 1.16.8 =
* Fix: Model list updated with correct cross-region inference IDs (us. prefix) for Claude 4.x models on AWS Bedrock
* New: Claude Sonnet 4.6 is now the default model (us.anthropic.claude-sonnet-4-6-20260301-v1:0)
* New: Added Claude Opus 4.6, Sonnet 4.5, Haiku 4.5 to model dropdown
* Fix: Claude 3.5 models kept as stable fallback options

= 1.16.7 =
* Fix: Test Connection now shows actionable guidance for "model identifier is invalid" error — explains the model must be enabled in AWS Bedrock Model Access console with a direct link
* Fix: Added friendly error messages for signature mismatch and access denied errors in Test Connection result

= 1.16.6 =
* Fix: Replaced invalid model IDs with confirmed available AWS Bedrock model IDs (default: Claude 3.5 Sonnet v2)
* New: AWS Bedrock connection status indicator showing connected/failed state in settings
* New: Test Connection button that fires a real API call with current form credentials before saving

= 1.16.5 =
* Fix: AWS SigV4 signature mismatch — colon in model ID (v1:0) now correctly percent-encoded as %3A in canonical URI, resolving "signature does not match" errors
* Fix: IAM permissions hint updated — clarifies that AmazonBedrockFullAccess is sufficient, no extra policy needed
* New: AWS Bedrock connection status indicator with Test Connection button in settings

= 1.16.4 =
* Fix: Canonical URL scheme now always matches site HTTPS/HTTP setting (prevents "Google chose different canonical" in Search Console)
* Fix: WordPress default `rel_canonical` removal now also hooks on `template_redirect` as a fallback for page builders and caching plugins
* Fix: Canonical output from Yoast, Rank Math, AIOSEO, The SEO Framework, and SEOPress is now always suppressed unconditionally (not just when "disable other SEO output" is on)
* New: Inline anchor wrapping for internal/external link suggestions (finds phrase in content and wraps with `<a>` tag)
* New: Broken links bulk redirect — select multiple broken links and redirect them all to a chosen URL
* New: All missing broken-link AJAX handlers implemented (get, scan, recheck, dismiss, undismiss)
* New: Canonical Health scanner in Search Performance — scan and auto-fix stored canonicals site-wide
* Fix: Canonical URL normalized (scheme + trailing slash) on save via meta box

= 1.16.3 =
* New: GSC site list is cached after OAuth for reliable dropdown loading
* New: "Load Site List" / "Refresh" button to fetch sites from GSC on demand via AJAX
* If dropdown loads → select your site and save
* If dropdown fails → manual text input available as fallback
* Site cache cleared on disconnect

= 1.16.2 =
* Fix: GSC `sc-domain:` site URLs were being destroyed by `esc_url_raw()` on save

= 1.16.1 =
* Fix: Site Property field now ALWAYS shows when GSC is connected
* Manual text input fallback when site list can't be loaded from GSC

= 1.16.0 =
* Fix: GSC auto-match now handles `sc-domain:` properties
* Better error messaging when GSC connects but site list fetch fails

= 1.15.9 =
* Fix: "Enhance with AI Suggestions" was calling non-existent method

= 1.15.8 =
* Fix: Force flyout link padding with !important to override WP admin CSS

= 1.15.7 =
* Fix: Flyout panel text padding increased

= 1.15.6 =
* Improvement: Flyout panels now match native WP admin submenu styling (dark background, same colors as the sidebar)

= 1.15.5 =
* Improvement: Flyout menu panels styling update

= 1.15.4 =
* Fix: Fetch Keywords 500 error — was calling non-existent method `search_analytics()` instead of `get_search_analytics()`
* Fix: Keyword tracker cron had the same wrong method call (also fixed)
* Fix: Added try/catch to keyword fetch handler so PHP errors return proper messages
* Improvement: Flyout menu panels now have more padding, spacing, rounded corners, and a subtle gap from the sidebar

= 1.15.3 =
* Redesign: Admin menu now uses hover flyout panels instead of collapsible groups
* Sidebar shows only Dashboard, 4 category groups, and Settings (6 items instead of 22)
* Hovering a group reveals a flyout panel with its sub-pages
* Fix: "Fetch Keywords Now" now shows actual GSC error messages instead of generic failure
* Fix: Content Tips loads instantly (rule-based) with optional "Enhance with AI" button
* AI suggestions load asynchronously and append below rule-based results

= 1.15.2 =
* Fix: Content Tips and Social Preview post search now works correctly
* Fix: Change History, Debug Log, and Background Jobs pages no longer stuck on loading
* Fix: Broken Links "Scan Now" now scans ALL posts (was only scanning 10)
* Fix: Schema page search compatibility with updated post search API
* New: Keyword Tracker "Fetch Keywords Now" button for immediate GSC data pull
* Fix: Post search no longer requires manage_options capability (works for editors too)

= 1.15.1 =
* Improvement: Admin sidebar menu now organized into 4 collapsible category groups
* Groups: Analyze & Fix, Technical SEO, Search & Social, System
* Click group headers to collapse/expand — state persists via localStorage
* Current page group auto-expands for seamless navigation
* Shorter menu labels for a cleaner sidebar
* Dashboard and Settings remain always visible

= 1.15.0 =
* NEW: Content Suggestions - AI-powered content improvement tips per post (structure, SEO, engagement, topical depth)
* NEW: Rule-based analysis engine - headings, images, alt text, internal/external links, keyword density, meta, lists
* NEW: AI analysis layer - OpenAI-powered content gap analysis when API key is configured
* NEW: WordPress Coding Standards Checker - self-audit plugin code against WP best practices
* NEW: Detects direct DB queries without prepare, unsanitized superglobals, missing ABSPATH checks, deprecated functions
* NEW: Code audit scores with file-by-file breakdown, expandable issue details with severity and line numbers
* NEW: Performance Profiler - tracks plugin load time (ms), DB queries, memory usage (KB), peak memory (MB)
* NEW: Performance history chart with rolling 200-sample trend visualization (Chart.js)
* NEW: Environment info panel - PHP/WP/MySQL versions, memory limits, active plugins, published posts
* NEW: Plugin database tables panel - row counts, data size, index size per table
* NEW: Three new dashboard navigation cards (Content Tips, Code Audit, Performance)
* Improvement: Performance data recorded per admin page load with automatic cleanup

= 1.14.0 =
* NEW: Content Readability Scoring - Flesch-Kincaid Reading Ease, grade level, sentence/paragraph analysis
* NEW: Readability suggestions - actionable tips for word count, sentence length, passive voice, transition words
* NEW: AJAX-powered readability analysis from the SEO Analyzer and meta box
* NEW: Social Preview Cards - dedicated admin page to preview and customize Facebook/Twitter sharing cards
* NEW: Custom OG Title, OG Description, OG Image overrides per post
* NEW: Custom Twitter Title, Twitter Description, Twitter Image overrides per post
* NEW: Live preview updates as you type - see exactly how your post will appear on social media
* NEW: Keyword Tracker - track search keyword rankings over time using Google Search Console data
* NEW: Daily cron fetches GSC keyword data (position, clicks, impressions, CTR) and stores historical snapshots
* NEW: Interactive position trend chart per keyword with Chart.js
* NEW: Keyword search, date range filter (7d/30d/90d), position badges (top 3/10/20)
* NEW: Dashboard navigation cards for Social Preview and Keyword Tracker
* Database: Added ssf_keyword_tracking table (auto-created via migration v6)
* IMPROVED: Social tags output now respects custom OG/Twitter overrides from the Social Preview editor

= 1.13.0 =
* NEW: Broken Link Checker - scans posts for dead links (404s, timeouts, connection errors)
* NEW: Background cron scans 5 posts per day automatically, cycles through all content
* NEW: Manual "Scan Now" checks 10 most recent posts on demand
* NEW: Recheck individual links, dismiss false positives, filter by type (internal/external)
* NEW: 404 Monitor - logs every real 404 hit on your site with hit counts and referrers
* NEW: One-click redirect creation from 404 entries (integrates with Redirects module)
* NEW: Smart noise filtering - ignores bots/scanners hitting common exploit paths
* NEW: robots.txt Editor - view, edit, and manage your site's robots.txt from the plugin
* NEW: "Load Recommended" template with optimized crawl rules and WooCommerce support
* NEW: Real-time validation warnings (blocks-all detection, missing sitemap, etc.)
* NEW: Physical file detection warning (if robots.txt file exists in site root)
* NEW: Dark-themed code editor for robots.txt content
* NEW: Dashboard navigation cards for all three new tools
* Database: Added ssf_broken_links and ssf_404_log tables (auto-created via migration v5)
* IMPROVED: DB Migrator updated to v5 for new tables

= 1.12.0 =
* NEW: Setup Wizard - guided first-run setup for API key, post types, and feature toggles
* NEW: Database Migration System - versioned schema updates that apply cleanly on plugin update
* NEW: Input Validator - centralized sanitization for all SEO titles, descriptions, URLs, API keys, and post types
* NEW: Automatic redirect to setup wizard on first plugin activation
* NEW: Skip option to bypass wizard and configure later in Settings
* IMPROVED: Settings save now uses strict validation (title length limits, URL format, allowed separators)
* IMPROVED: SEO data save uses dedicated validators for title, description, keyword, and URL fields
* IMPROVED: Post type selection validated against registered public post types
* IMPROVED: API keys stripped of non-printable characters
* Database: Versioned migration system tracks schema version and applies updates incrementally

= 1.11.0 =
* NEW: Background Job Queue - bulk operations (10+ posts) are automatically queued and processed in the background
* NEW: Job Queue admin page with real-time progress bars, cancel, and retry failed items
* NEW: API Rate Limiter - throttles OpenAI and GSC requests to prevent hitting rate limits
* NEW: Automatic retry with exponential backoff on rate limit (429) and server errors (5xx)
* NEW: Rate limit usage dashboard showing remaining requests per minute for OpenAI and GSC
* NEW: Dashboard navigation card for Background Jobs
* IMPROVED: Bulk AI Fix auto-routes to background queue when processing 10+ posts
* IMPROVED: Custom cron interval (every minute) for responsive job processing
* Database: Added ssf_jobs table for job tracking (auto-created on activation)

= 1.10.0 =
* NEW: Change History system - every AI/manual change is recorded with before/after values
* NEW: One-click Undo/Rollback - revert any change instantly from the Change History page
* NEW: Debug Log with admin viewer - errors, warnings, and info events logged to a dedicated page
* NEW: Automatic meta tracking via WordPress hooks - all _ssf_ meta changes captured without code duplication
* NEW: Source tagging - changes are tagged as AI, Manual, Bulk, Cron, or Orphan Fix for easy filtering
* NEW: Dashboard navigation cards for Change History and Debug Log
* IMPROVED: OpenAI API calls now log success/failure with token usage for monitoring
* IMPROVED: GSC API errors logged with request context for debugging
* IMPROVED: Plugin updater logs success/failure after install for troubleshooting
* Database: Added ssf_history and ssf_logs tables (auto-created on activation or admin visit)

= 1.9.0 =
* NEW: Google Search Console integration — connect your GSC account directly
* NEW: Search Performance dashboard — see clicks, impressions, CTR, and average position
* NEW: Top Search Queries table — see which keywords bring traffic
* NEW: Top Pages table — see which pages perform best in search
* NEW: Performance chart with daily clicks and impressions over time
* NEW: Submit sitemap to Google directly from the plugin
* NEW: URL Inspection API support — check if specific pages are indexed

= 1.8.7 =
* FIXED: Auto-updater zip packaging - was using backslash paths causing extraction failures on Linux servers
* Orphan fix now adds outgoing internal links within the page's own content (up to 3 relevant links)
* Fixes "No internal links found" SEO analysis warning on orphaned pages
* AI finds natural anchor text phrases and converts them to contextual links to related pages
* Improved post-install verification and error logging in updater

= 1.8.5 =
* FIX: Critical error from incorrect zip packaging in v1.8.3/v1.8.4
* Added defensive class_exists guards for SSF_Admin and SSF_Updater
* Fixed release zip to include proper folder structure for WordPress updater

= 1.8.3 =
* NEW: AI-powered internal linking for orphaned pages — automatically adds contextual links from relevant posts
* AI finds natural anchor text phrases in existing content and converts them into internal links
* Fallback to blog/contact page when no relevant content is found
* "Fix All with AI" bulk action processes all orphaned pages sequentially
* "Show All" button to view all items in any issue group (previously capped at 10)
* Removed trailing slash false positives from Indexability Auditor scan
* Trailing slashes are now auto-enforced by canonical/sitemap/OG — scan no longer flags them as issues
* Scan now displays a green "Automatically handled" status for trailing slash consistency

= 1.8.2 =
* FIX: Enforce trailing slash consistency in canonical tags, OG URLs, and sitemap URLs
* Prevents "Google chose different canonical" errors from slash mismatches
* Canonical and sitemap URLs now match WordPress permalink structure automatically

= 1.8.1 =
* FIX: SEO title not rendering in <title> tag on Elementor/custom themes
* Replaced fragile filter-chain approach with direct <title> tag output in wp_head (same approach as Yoast/Rank Math)
* Removed output buffer fallback — no longer needed with direct output
* Removed dependency on title-tag theme support for title rendering

= 1.8.0 =
* NEW: Each tool now has its own dedicated admin page instead of quick action buttons on the dashboard
* NEW: Bulk AI Fix page — full-page preview and fix workflow with post selection
* NEW: SEO Analyzer page — analyze/re-analyze posts, view scores, filter by status, paginated table
* IMPROVED: Dashboard is now a clean overview with stats and navigation cards to tool pages
* IMPROVED: Schema regeneration available on its own Schema Manager page
* Menu restructured: Dashboard > SEO Analyzer > Bulk AI Fix > All Posts > Local SEO > Schema > Redirects > Indexability Audit > Migration > Settings

= 1.7.1 =
* FIX: Bulk AI Fix preview now correctly detects posts with missing SEO data (was showing "nothing to fix" falsely)
* FIX: Replaced broken LEFT JOIN queries with reliable NOT EXISTS subqueries + TRIM for whitespace-only values
* FIX: Bulk fix now processes the exact posts user selected in preview instead of re-querying (selections were ignored)
* FIX: "Missing" filter now checks all three fields (title, description, keyword) instead of only title
* FIX: Count query was broken for DISTINCT queries, returning wrong total

= 1.7.0 =
* NEW: Redesigned Bulk AI Fix with preview-before-fix workflow
* NEW: See a full list of affected posts before running AI generation
* NEW: Select/deselect individual posts — only fix what you want
* NEW: Live progress with per-post results shown inside the modal
* NEW: Preview endpoint shows current SEO status (title, desc, keyword) for each post
* Improved: Quick Actions reorganized for clearer workflow

= 1.6.4 =
* Fix: Auto-updater now detects new versions from tags (no longer requires formal GitHub Release)
* Fix: Bulk AI generation ("Generate All Missing SEO") now works — was silently failing
* Fix: All AI handlers now validate API key upfront and return clear errors
* Fix: Empty AI responses no longer wipe existing SEO data (all handlers protected)
* Fix: Elementor/shortcode content now properly cleaned before sending to AI
* Fix: Individual AI fix buttons now show actual API errors instead of generic failure
* Fix: Post editor fix buttons validate API configuration before calling
* Hardened: All 8 AJAX handlers audited for empty-response and error-path bugs
* Improved: SQL queries use prepared statements for post type filtering

= 1.6.2 =
* Fix: "AI Unique Title" now generates a genuinely different title (tells AI to avoid repeating current one)
* Fix: "AI Unique Desc" now generates a genuinely different description
* Fix: UI no longer shows "Fixed!" when AI call fails — shows actual error message
* Fix: Validates AI returned something different before saving
* Improved: Higher temperature (0.9) for unique generation to ensure creative variation

= 1.6.1 =
* Fix: Resolved redirect loop on pages with year-prefixed slugs (e.g. 2025-scholarship)
* Fix: Removed custom redirect handler — now uses WordPress native redirect_canonical
* Performance: Removed permalink filters that ran on every link (major speed improvement)
* Performance: Output buffering now conditional (only for themes without title-tag support)
* Performance: Moved updater, post type detection to admin-only (no frontend overhead)
* Performance: Meta manager skips admin requests

= 1.6.0 =
* NEW: Comprehensive Indexability Auditor — detects all 9 Google Search Console issue types
* NEW: Blocked by robots.txt detection — parses robots.txt and flags published pages blocked from crawling
* NEW: Thin content detection — finds pages under 300 words (common "Crawled not indexed" cause)
* NEW: Duplicate title and description detection — prevents "Duplicate without canonical" issues
* NEW: Orphaned page detection — finds pages with no internal links pointing to them
* NEW: Missing SEO data detection — pages without title/description that Google may skip indexing
* NEW: Published pages with redirect detection — flags conflicting redirect/publish states
* NEW: One-click AI fix for missing SEO, duplicate titles, and duplicate descriptions
* NEW: Bulk AI fix — generate all missing SEO data across the site with one button
* NEW: Individual fix buttons for noindex removal, redirect chain flattening, and more
* Enhanced stat dashboard with missing SEO data and thin content counts
* Renamed "Search Console Fixer" to "Indexability Auditor" for clarity

= 1.5.0 =
* Added Search Console Fixer (trailing slashes, redirect chains, canonical conflicts)
* Added Search Console admin page with scan and auto-fix

= 1.4.1 =
* Fixed missing title tag on themes without title-tag support
* Added force title-tag theme support
* Added output buffer fallback for edge-case themes

= 1.4.0 =
* Added 4-layer gapless AI SEO generation system
* Auto-generate on publish AND update (not just first publish)
* Background WP-Cron runs twice daily for missing SEO
* Dashboard alert banner with one-click "Generate All Missing SEO"
* Accurate missing_titles and missing_descs counts

= 1.3.2 =
* Fixed missing meta title and og:image fallback chain

= 1.3.1 =
* Fixed fatal error on activation (mbstring dependency removed)
* Added defensive file_exists and class_exists checks

= 1.3.0 =
* Added GitHub auto-updater with private repo support
* Added GitHub token setting for authentication

= 1.2.0 =
* Added Readability Scoring (Flesch Reading Ease)
* Added Social Previews (Google, Facebook, Twitter)
* Added Redirect Manager with 404 tracking
* Added Breadcrumbs with Schema.org markup
* Added WooCommerce SEO integration

= 1.0.0 =
* Initial release

== Upgrade Notice ==

= 1.5.0 =
New Search Console Fixer detects and auto-fixes common indexing issues.

= 1.4.1 =
Critical fix for themes without title-tag support. Update immediately if your titles are missing.
