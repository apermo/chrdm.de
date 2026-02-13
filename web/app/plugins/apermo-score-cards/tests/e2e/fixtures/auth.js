/**
 * Authentication fixtures for E2E tests.
 */
import { test as base, expect } from '@playwright/test';

/**
 * Extend the base test with authentication helpers.
 */
export const test = base.extend( {
	/**
	 * Login as admin user.
	 */
	authenticatedPage: async ( { page }, use ) => {
		const baseURL = process.env.BASE_URL || 'https://apermo-score-cards.ddev.site';

		// Login to WordPress admin.
		await page.goto( `${ baseURL }/wp-login.php` );
		await page.fill( '#user_login', 'admin' );
		await page.fill( '#user_pass', 'admin' );
		await page.click( '#wp-submit' );

		// Wait for redirect to dashboard.
		await page.waitForURL( /wp-admin/ );

		await use( page );
	},
} );

export { expect };
