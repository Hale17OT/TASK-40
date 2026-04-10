import { defineConfig } from '@playwright/test';

export default defineConfig({
  testDir: '.',
  timeout: 90000,
  expect: { timeout: 15000 },
  fullyParallel: false,
  retries: 0,
  workers: 1,
  reporter: 'list',
  globalSetup: './global-setup.ts',
  use: {
    baseURL: process.env.BASE_URL || 'http://localhost:8080',
    trace: 'off',
    screenshot: 'off',
    video: 'on',
    viewport: { width: 1440, height: 900 },
    launchOptions: {
      slowMo: 250,
    },
  },
  projects: [
    {
      name: 'chromium',
      use: { browserName: 'chromium' },
    },
  ],
});
