# RS3 Wayfinder

Standalone RS3 Wayfinder foundation with Discord OAuth authentication, users, roles and permissions.

## Important web root

Set your web server document root to:

```text
/path/to/RS3-Wayfinder/public
```

Only files inside `public/` should be web accessible. Application code, config, storage and future services live outside the web root.

## Setup

1. Copy `.env.example` to `.env` in the project root.
2. Fill in your database and Discord OAuth values.
3. In the Discord Developer Portal, set your redirect URI to:

```text
https://your-domain.example/auth/callback.php
```

4. Visit:

```text
/setup/check.php
```

5. Log in with Discord.

## Current structure

```text
app/            Private application bootstrap, layout, libraries and schema
public/         Web root only
public/admin/   Admin user/permission screens
public/auth/    Discord OAuth login/callback/logout
public/assets/  Public CSS and future assets
storage/        Private runtime storage
```

## Notes

- The real `.env` file is intentionally not included in this package.
- Keep `.env`, `app/`, `storage/` and future vendor/config files outside the document root.

## Profiles update

This version adds user-owned RSN profiles.

After uploading, visit:

`/setup/check.php`

This creates/updates:

- `player_profiles`
- `player_profile_settings`
- profile admin permissions

Users can manage profiles at `/profiles/index.php`.
Admins can view all profiles at `/admin/profiles.php`.

## Profile Selector + RuneScape Avatars

This version adds an active profile selector to the authenticated top navigation. The selected profile is stored in the user's PHP session as `active_profile_id` and falls back to the user's primary profile when no active profile has been selected.

RuneScape character chat-head avatars are loaded using Jagex's public avatar endpoint:

`https://secure.runescape.com/m=avatar-rs/{RSN}/chat.png`

Only the generated URL is stored/rendered; no avatar image files are cached locally yet.

## RuneMetrics data collection

This build adds the first RuneMetrics data layer.

After upload, run:

```text
/setup/check.php
```

Then visit a profile via:

```text
/profiles/view.php
```

The app will sync RuneMetrics profile and quest data only when the profile cache is older than 15 minutes. It stores raw endpoint responses and parsed profile, skill, activity and quest data.


## Virtual skill levels

Skill display uses `app/lib/skills.php` to calculate RuneScape virtual levels from XP. Non-elite skills display virtual levels up to 120. Invention is treated as an elite skill and can display virtual levels up to 150. The stored RuneMetrics reported level remains unchanged; only the profile display is adjusted.

## RuneMetrics skill XP normalisation

RuneMetrics `skillvalues[].xp` is returned as actual XP multiplied by 10. This build normalises individual skill XP during ingestion before writing parsed rows to `player_latest_skills` and `player_skill_snapshots`.

Raw API JSON remains untouched in `runemetrics_fetches`.

Running `/setup/check.php` also applies a one-time migration to repair skill XP rows created by the previous build.
