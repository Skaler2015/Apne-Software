# ApneSoftware Analytics Backend — Setup Guide

Follow these steps in order on your Hostinger Cloud Startup hosting.

## Step 1 — Create the database

1. Log in to hPanel → Databases → MySQL Databases
2. Create a new database (note the full name, e.g. `u123456789_apnesoftware`)
3. Create a database user with a strong password, and attach it to the database with ALL PRIVILEGES
4. Note down: database name, username, password, host (almost always `localhost` on Hostinger)

## Step 2 — Import the table structure

1. In hPanel, open phpMyAdmin for your new database
2. Click the "Import" tab
3. Choose the file `backend/db_schema.sql` from this package
4. Click "Go" — this creates all 6 tables (tools, tool_views, tool_runs, daily_stats, geoip_cache, admin_users)

## Step 3 — Fill in your real credentials

1. Open `backend/config.php` in a text editor
2. Replace these four lines with your real values from Step 1:
   ```
   define('DB_NAME', 'your_real_database_name');
   define('DB_USER', 'your_real_username');
   define('DB_PASS', 'your_real_password');
   ```
3. Also change `ADMIN_PASSWORD` to a password only you know — this single password now protects
   BOTH the tools manager and the analytics dashboard, which live together at one URL (see Step 6)
4. **Never share this file or paste its contents anywhere public.**

## Step 4 — Upload everything

Upload the entire site (including the new `backend/` and `skaler2015/` folders) to `public_html`
on Hostinger, exactly like you've done before.

## Step 5 — Sync your tools into the database

Visit this URL once in your browser (replace with your real domain):
```
https://apnesoftware.com/backend/sync_tools.php
```
You should see "Sync complete" with a count of tools added. This only needs to be run again if you add new tools later through the old JSON file directly — adding tools through the new admin panel keeps this in sync automatically going forward.

## Step 6 — Log in to the admin panel

Visit:
```
https://apnesoftware.com/skaler2015/
```
Log in with the `ADMIN_PASSWORD` you set in Step 3. This one login now covers everything —
"Manage Tools" (add/edit/publish/delete tools and categories — saves straight to the live
`assets/tools-data.json`, no more downloading and re-uploading a file) and the full Analytics
dashboard, both reachable from the same sidebar.

## How tracking works (nothing else to set up)

`assets/common.js` already calls the tracking endpoint automatically on every tool page —
a "view" the moment the page loads, and a "run" whenever someone clicks a primary action
button (Merge, Convert, Calculate, Generate, etc). No changes needed to any of the 103
individual tool pages.

## If something doesn't work

- Dashboard shows "Could not connect to the database" → double-check the 3 values in `config.php`
- Numbers stay at zero after visiting tool pages → open your browser's DevTools → Network tab on
  a tool page, reload, and check the `track.php` request for an error response
- GeoIP (country/city) shows blank → this is normal for the first visit from any IP (cached after
  that), and won't work at all if you're testing from `localhost`/a private network IP

## What was NOT built (be aware)

- True real-time push (WebSockets) — the "Real-Time" page instead polls every 8 seconds, which
  looks and feels live but is technically short-interval polling, much simpler to host reliably
- True .xlsx Excel export — CSV export is provided instead (opens perfectly in Excel/Sheets);
  a real .xlsx library can be added later if you specifically need native Excel formatting
- PDF report export — use your browser's Print → Save as PDF on any report page for now
