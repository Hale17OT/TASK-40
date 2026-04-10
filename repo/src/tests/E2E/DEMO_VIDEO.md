# Demo Video Automation

This workflow records all Playwright E2E flows and stitches them into one demo video.

## Prerequisites

- App running at `http://localhost:8080`
- Node modules installed in `src/tests/E2E`
- Playwright Chromium installed
- `ffmpeg` available on PATH

## One-command run

```bash
cd src/tests/E2E
npm run demo:video
```

## Outputs

- Individual test clips: `src/tests/E2E/test-results/**/video.webm`
- Final stitched demo: `src/tests/E2E/test-results/demo-video/harborbite-demo.mp4`

## Notes

- The demo config uses `slowMo: 250` and runs serially so the UI is easy to follow.
- Since all feature specs run, the final output should comfortably exceed 3 minutes.
