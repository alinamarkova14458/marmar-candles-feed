# Pinterest scheduler (free, replaces Metricool for jalina.art)

How it works: `.github/workflows/pinterest-scheduler.yml` runs every 20 minutes, reads
`queue/*.json` and publishes pins whose time has come via Pinterest API v5.
`published.json` is the log (written by the workflow, do not edit by hand).

## Adding pins
Put a JSON list into `queue/<YYYY-MM-DD>-<topic>.json` on `master`:

```json
[{"date": "2026-11-20T16:00:00", "board": "679128887499302730",
  "title": "Christmas Gnome Clipart PNG: 8 Bundles", "text": "Pin description",
  "media": "https://jalina.art/pins/daily-x-list.jpg",
  "link": "https://jalina.art/x/?utm_source=pinterest&utm_medium=pin&utm_campaign=daily-x",
  "alt": "optional alt text"}]
```

- `date` without offset = Riga time. Same format as `affiliate/pins/daily/*-schedule.json`.
- Image must be a public URL (jalina.art/pins/...). Images only for now; video pins are not supported yet.
- Rules built in: max 15 pins per Riga day (counted from `published.json`), max 3 per run,
  pins more than 6 hours late are skipped (logged, not published).

## Settings (GitHub → Settings → Secrets and variables → Actions)
- Secrets: `PINTEREST_APP_ID`, `PINTEREST_APP_SECRET`, `GH_SECRETS_PAT`
  (fine-grained token, this repo only, permission "Secrets: read and write"),
  `PINTEREST_REFRESH_TOKEN` (filled by the "Pinterest connect" workflow).
- Variable `PINTEREST_ENV`: `sandbox` while the app has Trial access (pins visible only to you),
  `production` after Standard access.
- Without secrets the scheduler only logs what it would publish (dry run).

## One-time connect
1. App redirect URI in developers.pinterest.com: `https://jalina.art/`
2. Open (replace APP_ID):
   `https://www.pinterest.com/oauth/?client_id=APP_ID&redirect_uri=https%3A%2F%2Fjalina.art%2F&response_type=code&scope=boards:read,boards:write,pins:read,pins:write,user_accounts:read`
3. Allow, copy the `code=` value from the jalina.art address bar, run the "Pinterest connect"
   workflow with it within a few minutes.
