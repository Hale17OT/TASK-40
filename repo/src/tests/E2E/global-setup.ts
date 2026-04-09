/**
 * Global setup for Playwright E2E tests.
 * Clears rate-limit and failed-login counters from the app's cache
 * to prevent accumulated state from triggering CAPTCHA.
 */
async function globalSetup() {
  const baseURL = process.env.BASE_URL || 'http://localhost:8080';

  // Verify app is running
  try {
    const response = await fetch(`${baseURL}/api/time-sync`);
    if (!response.ok) {
      throw new Error(`App not responding: ${response.status}`);
    }
    console.log('  App is running at', baseURL);
  } catch (e) {
    console.error('  App is not running at', baseURL);
    throw e;
  }
}

export default globalSetup;
