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
