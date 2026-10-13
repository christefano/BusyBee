# Changelog

## v1.0.1

- Added screenshots.
- Added a "What's being worked on?" question to show what's changed in the last 30 days. It show any new edits, moves, new comments, subtasks, attached files, or if a task is closed.
- Fixed "N tasks in N folders" merging every project that isn't in a folder.
- When using [SortaFolders](https://github.com/christefano/SortaFolders) v1.0.1 or later, "What is blocked?" shows "A task in a project you cannot see" for a blocker in a project that hidden by SortaFolders (except for admins, who can see everything).
- The answer pager's Previous and Next links no longer have hover text that just repeated the link text.
- Fixed the missing visible outline for question buttons when they're selected from the keyboard with the Tab key.
- Fixed the "What is blocked?" question bubble showing "A task in a project you cannot see" for a blocker in a project that you can open when that project is in another folder or is closed.
- Fixed a question link dropping the sort order, direction, and page of the list under the bar.
- The question icon and the answer icon on the settings page are each their own fieldset with their own legend.
- An answer loads at most 25 rows from the database, and answers now have a pager if there are more than 25 rows.
- Fixed the bar not showing on *My* dashboard pages when SortaFolders is installed but turned off, which only happens on a database it doesn't support.
- The plugin description names the Overview page.
- Docs rewrite: INSTALL and README have typos fixed.
- Added tests.

## v1.0.0

First initial release.
