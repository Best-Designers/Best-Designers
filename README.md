# Best Designers SEO Client Dashboard Plugin

WordPress plugin for creating client SEO dashboards with live Google Analytics 4 + Search Console data.

## Features
- Manual client profile creation in WP Admin (`SEO Dashboards > Add Client`) with optional Industry label
- Backend fields for Project Manager name/email and client-specific SERanking public report URL
- Per-client long-tail public dashboard URL (`/client-dashboard/<token>/`)
- Noindex/noarchive controls on public dashboard responses
- Dashboard modules:
  - Top 20 page titles by GA4 views (property can be auto-resolved from Measurement ID or Stream ID)
  - 30-day and 90-day organic traffic comparisons
  - Overall Search Console CTR % card (30-day vs previous 30-day comparison)
  - AI Results Tracker card using GA4 referral-source sessions from major AI assistants (30-day vs previous 30-day comparison)
  - Search Console performance chart with 30-day / 3-month / 6-month filter (Clicks left, Impressions right)
  - Top 20 real Search Console queries with click counts
  - Google Business Profile performance overview section (Overview, Calls, Directions, Website Clicks)
  - Optional Google Merchant chart: "Your performance on Google last 28 days" (hidden when merchant account is not configured)
  - Client-specific logo rendering in dashboard header (fallback to default B logo)
  - Print / PDF export using browser print support
  - Project manager contact and SERanking report CTA for retention-focused client reporting

## Install
1. Copy plugin files to `wp-content/plugins/best-designers-seo-dashboard`.
2. Activate **Best Designers SEO Client Dashboard**.
3. Go to **SEO Dashboards > Settings** and add:
   - Google OAuth Client ID
   - Google OAuth Client Secret
   - Google OAuth Refresh Token
4. Create clients in **SEO Dashboards > Add Client** and set:
   - GA4 Property ID (optional if Measurement ID or Stream ID is provided)
   - Measurement ID and/or Stream ID (used to auto-resolve property when needed)
   - Search Console site URL (`sc-domain:example.com` or URL-prefix property)
   - Google Business Profile location (either Business Profile ID like `1234567890` or resource name like `locations/1234567890`)
   - Merchant Center account ID (optional; falls back to plugin-level default Merchant ID)
   - Client logo URL (optional; shown in place of the default logo without the black background tile)
   - Client Industry (optional; shown on dashboard)
   - Project Manager name + email (shown with an email button on the client dashboard)
   - SERanking public keyword report URL (shown as a professional external report CTA)

## Required Google OAuth scopes
- `https://www.googleapis.com/auth/analytics.readonly`
- `https://www.googleapis.com/auth/webmasters.readonly`
- `https://www.googleapis.com/auth/business.manage`
- `https://www.googleapis.com/auth/content`

## Notes
- Dashboard results are cached for 1 hour via transients.
- Refresh client data by updating/saving the client profile.
