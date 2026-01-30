# SkywarnPlus-NG support in AllScan

This guide explains how to turn on weather alerts from [SkywarnPlus-NG](https://github.com/hardenedpenguin/SkywarnPlus-NG) and adjust AllScan so the main page shows live alert text from your node’s SkywarnPlus API.

## What you need first

- **SkywarnPlus-NG** installed and running on your node (or another host on your network), exposing its HTTP API. A typical default is `http://localhost:8100` when SkywarnPlus-NG listens on the same machine as AllScan.
- **AllScan** with a working login; only **admin** users can change global configuration on the **Cfgs** tab.

AllScan does not install or configure SkywarnPlus-NG itself—it only **reads** the JSON status from the URL you set.

## Where to configure

1. Sign in to AllScan.
2. Open **Cfgs** (configuration management).
3. Use **Edit Cfg** to change the parameters below.

SkywarnPlus-related settings appear in the configuration table and in the **Cfg Name** dropdown when you edit settings.

## Main switch and API URL

| Setting (label in AllScan) | Purpose |
|----------------------------|---------|
| **SkywarnPlus Enable** | **Off** / **On**. When **On**, AllScan shows a Skywarn line on the **main** page and can optionally poll that API in the background. |
| **SkywarnPlus API URL** | Base URL of your SkywarnPlus-NG service (no path required). Example: `http://localhost:8100` or `http://192.168.1.50:8100`. AllScan appends `/api/status` to fetch alerts. |

**Defaults (new installs):** SkywarnPlus is **Off**; the API URL defaults to `http://localhost:8100` in the configuration model—set it to match your real service.

After you set **SkywarnPlus Enable** to **On** and save, reload the main AllScan page. You should see a line such as “SkyWarn+NG: …” above the Favorites section (or status messages if the API is unreachable).

## Polling options (visible when SkywarnPlus is enabled)

When **SkywarnPlus Enable** is **On**, two extra options appear in Cfgs:

| Setting | Purpose |
|---------|---------|
| **Poll SkywarnPlus API** | **Off** / **On**. When **On**, the browser asks AllScan periodically for updated alert text **without** reloading the whole page. When **Off**, alerts update only when you load or refresh the page. |
| **SkywarnPlus Poll Interval (minutes)** | How often to refresh the alert line when polling is **On**. Allowed range: **1–1440** minutes. **Default: 3** minutes. |

Polling uses your normal AllScan session; you must be allowed to use the site (same rules as viewing the main page).

## Quick setup checklist

1. Confirm SkywarnPlus-NG is running and reachable from the web server (e.g. `curl http://127.0.0.1:8100/api/status` on the node).
2. In AllScan **Cfgs**, set **SkywarnPlus API URL** to that base URL.
3. Set **SkywarnPlus Enable** to **On** and save.
4. Optional: leave **Poll SkywarnPlus API** **On** and set **SkywarnPlus Poll Interval (minutes)** as you like (default 3).
5. Open the main AllScan page and confirm the Skywarn line appears and updates (wait one poll interval if you enabled polling).

## Status messages you might see

AllScan shows plain-language status on the main page, for example:

- **API URL not configured** — Set **SkywarnPlus API URL** in Cfgs.
- **API Offline** — AllScan could not open the status URL (service down, wrong host/port, or firewall).
- **API Error** — The response was not valid JSON.
- **No Alerts** — The API responded successfully; there are no active alerts in the payload AllScan uses.
- Otherwise, up to three alert events may be listed with severity-based coloring.

## Resetting to defaults

Use **Edit Cfg**, choose the parameter, then **Set to Default Value** where available, or set:

- **SkywarnPlus Enable:** Off  
- **SkywarnPlus API URL:** `http://localhost:8100`  
- **Poll SkywarnPlus API:** On  
- **SkywarnPlus Poll Interval (minutes):** 3  

Save after changes.
