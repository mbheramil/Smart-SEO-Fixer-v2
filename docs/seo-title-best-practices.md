# Why We Changed the SEO Title Format

## Previous Format (Yoast SEO)

```
Site Title | Phone Number | Website Name
Example: Aronberg Law | 561-555-1234 | Aronberg & Aronberg
```

## New Format (Smart SEO Fixer)

```
Primary Keyword - Compelling Benefit | Brand Name
Example: Boynton Beach Personal Injury Lawyer | Aronberg Law
```

---

## Why the Old Format Was Bad for SEO

### 1. Wasted Character Space
Google only displays **55-60 characters** in search results. A phone number consumes 12-15 characters — roughly 25% of the available title space — on information that provides zero ranking value.

### 2. No Keyword Relevance
Google uses the `<title>` tag as a **primary ranking signal**. When someone searches "personal injury lawyer boynton beach," Google checks your title for matching keywords. A title filled with your brand name and phone number tells Google nothing about the page's topic, making it harder to rank.

### 3. Nobody Searches for Phone Numbers
No user types a phone number into Google to find a blog post or service page. Including it in the title serves no search intent.

### 4. Duplicate/Boilerplate Title Problem
When every page follows the same `[Title] | Phone | Brand` pattern, Google sees hundreds of near-identical titles across the site. This is a known negative signal called **"boilerplate repetition."** Google may choose to rewrite your titles in search results when it detects this pattern.

### 5. Lower Click-Through Rates (CTR)
Users scanning search results decide in milliseconds. A title showing a phone number looks spammy and provides no reason to click. Better titles lead to higher CTR, which Google uses as a quality signal.

**Comparison:**
```
❌ Aronberg Law | 561-555-1234 | Aronberg & Aronberg
✅ Boynton Beach Personal Injury Lawyer — Free Consultation
```

---

## Where Phone Numbers Should Go Instead

| Location | Why It Works |
|----------|-------------|
| **LocalBusiness Schema (JSON-LD)** | Google extracts this and shows a click-to-call button directly in search results. Smart SEO Fixer handles this automatically. |
| **Google Business Profile** | The #1 place users look for business phone numbers. Shows in Google Maps and the Knowledge Panel. |
| **Meta Description** | Acceptable at the end of the description (e.g., "Call 561-555-1234 for a free consultation"). Google sometimes bolds phone numbers in descriptions. |
| **Website Header/Footer** | Visible on every page for visitors who are already on the site. |

---

## What Makes a Good SEO Title

### Formula
```
[Target Keyword] - [Benefit/Differentiator] | [Brand Name]
```

### Rules
- **50-60 characters max** — anything longer gets truncated in search results
- **Primary keyword first** — front-loading keywords gives them more weight
- **One brand mention** — at the end, separated by a pipe `|` or dash
- **Unique per page** — every page should have a distinct title reflecting its specific content
- **Action-oriented when possible** — words like "Free Consultation," "Near Me," "Expert" improve CTR

### Good Examples (Law Firm)
```
Personal Injury Lawyer in Boynton Beach | Aronberg Law
Florida E-Bike Accident Attorney — Know Your Rights | Aronberg Law
Car Accident Claims in Palm Beach County | Free Case Review
Workers' Compensation Lawyer | Aronberg & Aronberg
```

### Bad Examples
```
Aronberg Law | 561-555-1234 | Aronberg & Aronberg          ← no keywords
Home | Aronberg Law                                          ← too generic
Blog Post Title                                              ← no brand, no keyword optimization
Aronberg Law Firm Website Page About Our Legal Services      ← keyword stuffing, too long
```

---

## How Smart SEO Fixer Handles Titles

1. **AI-Generated Titles** — When you click "Generate Title" or use "Bulk AI Fix," the AI creates keyword-focused, properly-sized titles based on the actual content of each page.

2. **Title Separator Setting** — Configured in Settings > General. Controls the character between the page title and site name (e.g., `|`, `–`, `—`).

3. **Per-Post Override** — Every post has an SEO Title field in the meta box where you can write a custom title or regenerate with AI.

4. **Auto-Generation on Publish** — When "Auto Meta Generation" is enabled in Settings, AI automatically creates an SEO title when a new post is first published.

---

## References

- [Google's Documentation on Title Links](https://developers.google.com/search/docs/appearance/title-link)
- [Moz Title Tag Best Practices](https://moz.com/learn/seo/title-tag)
- [Ahrefs: How to Write the Perfect SEO Title Tag](https://ahrefs.com/blog/title-tag-seo/)

---

*Document created: February 2026*
*Plugin: Smart SEO Fixer v1.0.0*
