# Best Designers SEO Client Dashboard Plugin

WordPress plugin for creating client SEO dashboards with live Google Analytics 4 + Search Console data.

## Features
- Manual client profile creation in WP Admin (`SEO Dashboards > Add Client`)
- Per-client long-tail public dashboard URL (`/client-dashboard/<token>/`)
- Noindex/noarchive controls on public dashboard responses
- Dashboard modules:
  - Top page titles by GA4 views
  - 30-day and 90-day organic traffic comparisons
  - Search Console performance chart (clicks, impressions, CTR, position)
  - Search Console top queries table

## Install
1. Copy plugin files to `wp-content/plugins/best-designers-seo-dashboard`.
2. Activate **Best Designers SEO Client Dashboard**.
3. Go to **SEO Dashboards > Settings** and add:
   - Google OAuth Client ID
   - Google OAuth Client Secret
   - Google OAuth Refresh Token
4. Create clients in **SEO Dashboards > Add Client** and set:
   - GA4 Property ID
   - Optional Measurement ID / Stream ID (reference)
   - Search Console site URL (`sc-domain:example.com` or URL-prefix property)

## Required Google OAuth scopes
- `https://www.googleapis.com/auth/analytics.readonly`
- `https://www.googleapis.com/auth/webmasters.readonly`

## Notes
- Dashboard results are cached for 1 hour via transients.
- Refresh client data by updating/saving the client profile.
