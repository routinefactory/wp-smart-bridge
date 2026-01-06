# Release & Versioning Protocol

## Overview
To ensure efficiency and coherence, `wp-smart-bridge` uses a **Centralized Versioning Strategy**. This eliminates the risk of human error during updates and removes the need for manual edits across multiple files or external batch scripts.

## Single Source of Truth
The version number is defined in exactly **one** place for code logic:

*   **File**: `wp-smart-bridge/wp-smart-bridge.php`
*   **Constant**: `SB_VERSION`

```php
define('SB_VERSION', '3.0.1');
```

## How It Works (Automatic Propagation)
All CSS, JavaScript, and Cache Busting logic automatically inherit this constant.

*   **Assets**: `admin/class-sb-admin.php` dynamically uses `SB_VERSION` when enqueuing:
    ```php
    wp_enqueue_style('sb-admin', '...', [], SB_VERSION); // Auto-updates query string ?ver=3.0.1
    ```
*   **Database**: The upgrader (`maybe_upgrade_database`) compares `SB_VERSION` against the database option to trigger migrations automatically.

## Release Checklist (Optimized)
When releasing a new version, you only need to modify **2 files**:

1.  **`wp-smart-bridge/wp-smart-bridge.php`**
    *   Update `Version: x.x.x` in the file header (Required by WordPress).
    *   Update `define('SB_VERSION', 'x.x.x')`.

2.  **`wp-smart-bridge/readme.txt`**
    *   Update `Stable tag: x.x.x`.
    *   Add entry to `== Changelog ==`.

> **Note**: You do **NOT** need to update the `@since` or `@version` tags in individual CSS/JS/PHP class files for every release. These tags are for historical documentation of when a file was introduced or significantly refactored, not current version tracking.

## Git Commands for Release
```bash
git add wp-smart-bridge/wp-smart-bridge.php wp-smart-bridge/readme.txt
git commit -m "Bump version to vX.X.X"
git tag vX.X.X
git push origin main
git push origin vX.X.X
```
