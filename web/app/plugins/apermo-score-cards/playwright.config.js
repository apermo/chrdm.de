/**
 * Playwright configuration for Apermo Score Cards E2E tests.
 *
 * @see https://playwright.dev/docs/test-configuration
 */
import { defineConfig, devices } from '@playwright/test';

/**
 * Read environment variables from .env file.
 */
const baseURL = process.env.BASE_URL || 'https://apermo-score-cards.ddev.site';

export default defineConfig( {
	testDir: './tests/e2e',
	fullyParallel: true,
	forbidOnly: !! process.env.CI,
	retries: process.env.CI ? 2 : 0,
	workers: process.env.CI ? 1 : undefined,
	reporter: process.env.CI ? 'github' : 'html',
	use: {
		baseURL,
		trace: 'on-first-retry',
		screenshot: 'only-on-failure',
	},

	projects: [
		{
			name: 'chromium',
			use: { ...devices[ 'Desktop Chrome' ] },
		},
		{
			name: 'firefox',
			use: { ...devices[ 'Desktop Firefox' ] },
		},
		{
			name: 'webkit',
			use: { ...devices[ 'Desktop Safari' ] },
		},
		{
			name: 'mobile-chrome',
			use: { ...devices[ 'Pixel 5' ] },
		},
	],

	// Configure web server for local development.
	webServer: process.env.CI
		? undefined
		: {
				command: 'ddev start',
				url: baseURL,
				reuseExistingServer: true,
				timeout: 120000,
		  },
} );
