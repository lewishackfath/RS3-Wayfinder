# RS3 Wayfinder

Standalone RS3 journey tracker foundation.

## Setup

1. Upload the files to your web root.
2. Copy `.env.example` to `.env` and fill in database and Discord OAuth details.
3. In the Discord Developer Portal, add your redirect URI exactly as:
   `https://your-domain.com/auth/callback.php`
4. Visit `/setup/check.php` once to bootstrap the database.
5. Log in via Discord.

## Admin access

Add your Discord user ID to `ADMIN_DISCORD_IDS` in `.env` before first login. Multiple IDs can be comma-separated.

## OAuth scopes

This app only requests `identify email`.
