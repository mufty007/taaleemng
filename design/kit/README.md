# Ta’aleem UI kit

One folder to copy into another project:

```
design/kit/
  DESIGN.md      ← design rules
  theme.css      ← Tailwind v4 tokens + base styles
  components/    ← Blade component copy (from resources/views/components)
  README.md      ← this file
```

See `DESIGN.md` for install steps and which components are generic vs app-specific.

**Note:** This is a snapshot. After UI changes in the live app, re-copy `resources/views/components/` and sync `theme.css` from `resources/css/app.css` `@theme` if tokens change.
