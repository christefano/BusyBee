# Installation

## Requirements

- Kanboard 1.2.54 or later.
- Nothing else, and BusyBee works with or without other plugins.
- Optional: SortaFolders renders the `template:sortafolders:folder:before-tabs` and `template:sortafolders:dashboard:before-tabs` hooks. With it the bar also shows above *My* folders and BusyBee uses its folder term.
- No tables and no database changes.


## Install

1. Put the `BusyBee` folder into `/path/to/kanboard/plugins/`.
2. Reload PHP-FPM if OPcache is on and a changed file doesn't show up, since OPcache can serve the old copy for a few minutes. A first install has no old copy, so it loads right away.
3. Open *Settings -> BusyBee* to configure BusyBee's questions and icons.

## Remove

Delete `plugins/BusyBee`. There are no tables to drop. The `busybee_ask_` and `busybee_` icon settings stay in the settings table and do nothing. To remove them too, run this against Kanboard's database after deleting the plugin folder:

```sql
DELETE FROM settings WHERE `option` LIKE 'busybee%';
```

The questions disappear from every page and nothing else changes. The `sortafolders_ask_` settings from before the move may still be in the table.


## Update

Copy the new `BusyBee` folder over the old one. The saved settings live in Kanboard's settings table, so they'll survive the plugin update.


## Conflicts

- Without SortaFolders, or when SortaFolders is switched off because it doesn't support the database, BusyBee overrides Kanboard core's `dashboard/layout` template. It does that in `onStartup()`, after every plugin has loaded. Another plugin that overrides the same template can conflict with it, and the plugin that sets its override last takes priority. Kanboard doesn't guarantee the load order.
- Another plugin that registers a controller named `SettingsController` with its own access rule clashes with BusyBee's settings page, since Kanboard's access rules go by the controller's name.
