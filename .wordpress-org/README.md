# Listing assets

Everything here is what the WordPress.org plugin page shows. None of it ships in the plugin zip — [`.distignore`](../.distignore) excludes this directory, and `10up/action-wordpress-plugin-deploy` uploads it to SVN `assets/` separately.

**Every file here is currently a generated placeholder.** Replace them in place, keeping the exact filenames.

| File | Size | Must show |
|---|---|---|
| `screenshot-1.JPG` | 1280px wide or wider | The widget on a published page, real dates and prices |
| `screenshot-2.JPG` | same | The block placed on a page in the editor |
| `screenshot-3.JPG` | same | The block sidebar with the widget dropdown open |
| `screenshot-4.JPG` | same | Settings → Cabintale, with a preview open |
| `screenshot-5.JPG` | same | The Cabintale approval screen naming the site |
| `banner-1544x500.png` | exactly 1544×500 | Listing header |
| `banner-772x250.png` | exactly 772×250 | The same artwork at 1× |
| `icon-256x256.png` | exactly 256×256 | Square icon |
| `icon-128x128.png` | exactly 128×128 | The same icon at 1× |

Things that are easy to get wrong:

- **Screenshot numbers are positional.** `screenshot-3.JPG` is described by the third line under `== Screenshots ==` in [`readme.txt`](../readme.txt). Reordering the images without reordering the captions mislabels them on the listing.
- **The banner crops.** WordPress.org shows the middle of it on narrow screens, so anything near the left or right edge disappears.
- **These files are the only images the listing can show.** Inline images in the description do not render — no `![]()`, no `<img>`. If a picture matters, it belongs here as a screenshot.
- **A video is the exception.** A YouTube or Vimeo URL on its own line inside `== Description ==` is auto-embedded, as are `[youtube URL]`, `[vimeo URL]` and `[wpvideo ID]`. It cannot replace a screenshot, and raw embed HTML is rejected.
