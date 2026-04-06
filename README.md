# ZipMaster

ZipMaster is a single-file PHP tool that lets you create `.zip` archives directly from your webserver's file browser. It lists all files and folders in the current directory, lets you select what to include, name your archive, and download or delete it — all from a clean dark UI in your browser.

It's handy when you don't have shell access and need to package files server-side (e.g. backing up a PHP project or bundling an image collection) without downloading everything first.

## Requirements

- PHP 7.4 or newer
- `ZipArchive` extension enabled (bundled with most shared hosting providers)

## Usage

1. Upload `zipmaster.php` to the directory you want to zip files from.
2. Open your browser and navigate to the URL of `zipmaster.php`.

### Creating an archive

1. Browse the file list — folders appear first, files below.
2. Check the files and/or folders you want to include.
   - Use **Select All**, **Deselect All**, or **Invert** buttons to quickly manage selection.
3. (Optional) Type a name in the **Archive name** field. If left blank, it defaults to `archive_YYYYMMDD_HHMMSS`.
4. Click **Create ZIP**.
5. Once created, a download link appears in the success message.

### Downloading or deleting an archive

- Existing `.zip` files in the directory show two action buttons in their row:
  - **⬇ DL** — download the archive directly.
  - **✕** — delete the archive from the server (a confirmation prompt appears).

## Security

- Path traversal is blocked: only files within the current directory can be added to an archive.
- Only `.zip` files can be downloaded or deleted through the interface.



## License

Released under GNU/GPL v3
