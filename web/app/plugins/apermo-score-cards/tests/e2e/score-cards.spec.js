/**
 * E2E tests for Apermo Score Cards plugin.
 */
import { test, expect } from './fixtures/auth';

test.describe( 'Score Cards Plugin', () => {
	test.describe( 'Plugin Activation', () => {
		test( 'plugin is active on plugins page', async ( { authenticatedPage } ) => {
			await authenticatedPage.goto( '/wp-admin/plugins.php' );

			// Look for the plugin row.
			const pluginRow = authenticatedPage.locator(
				'[data-plugin="apermo-score-cards/apermo-score-cards.php"]'
			);
			await expect( pluginRow ).toBeVisible();

			// Check that it shows as active.
			const deactivateLink = pluginRow.locator( 'a.deactivate' );
			await expect( deactivateLink ).toBeVisible();
		} );
	} );

	test.describe( 'Test Page', () => {
		test( 'score cards test page exists', async ( { page } ) => {
			await page.goto( '/score-cards-test/' );

			// Page should load without 404.
			await expect( page ).not.toHaveTitle( /Page not found/i );
			await expect( page ).not.toHaveTitle( /404/i );
		} );

		test( 'wizard block renders on test page', async ( { page } ) => {
			await page.goto( '/score-cards-test/' );

			// Look for the wizard block wrapper.
			const wizardBlock = page.locator( '.asc-wizard' );
			await expect( wizardBlock ).toBeVisible();
		} );
	} );

	test.describe( 'REST API', () => {
		test( 'players endpoint returns data', async ( { authenticatedPage } ) => {
			const response = await authenticatedPage.request.get(
				'/wp-json/apermo-score-cards/v1/players'
			);

			expect( response.ok() ).toBeTruthy();

			const data = await response.json();
			expect( Array.isArray( data ) ).toBeTruthy();
			// Should have at least the test users created by orchestrate.
			expect( data.length ).toBeGreaterThan( 0 );
		} );

		test( 'players have expected properties', async ( { authenticatedPage } ) => {
			const response = await authenticatedPage.request.get(
				'/wp-json/apermo-score-cards/v1/players'
			);

			const data = await response.json();
			const player = data[ 0 ];

			// Check player object structure.
			expect( player ).toHaveProperty( 'id' );
			expect( player ).toHaveProperty( 'name' );
		} );
	} );

	test.describe( 'Block Editor', () => {
		test( 'wizard block is available in block inserter', async ( { authenticatedPage } ) => {
			// Create a new post.
			await authenticatedPage.goto( '/wp-admin/post-new.php' );

			// Wait for editor to load.
			await authenticatedPage.waitForSelector( '.edit-post-header' );

			// Open block inserter.
			const inserterButton = authenticatedPage.locator(
				'button[aria-label="Toggle block inserter"]'
			);
			await inserterButton.click();

			// Search for wizard block.
			const searchInput = authenticatedPage.locator(
				'.block-editor-inserter__search input'
			);
			await searchInput.fill( 'wizard' );

			// Should find the wizard score card block.
			const wizardBlock = authenticatedPage.locator(
				'.block-editor-inserter__panel-content button:has-text("Wizard Score Card")'
			);
			await expect( wizardBlock ).toBeVisible();
		} );

		test( 'darts block is available in block inserter', async ( { authenticatedPage } ) => {
			await authenticatedPage.goto( '/wp-admin/post-new.php' );
			await authenticatedPage.waitForSelector( '.edit-post-header' );

			const inserterButton = authenticatedPage.locator(
				'button[aria-label="Toggle block inserter"]'
			);
			await inserterButton.click();

			const searchInput = authenticatedPage.locator(
				'.block-editor-inserter__search input'
			);
			await searchInput.fill( 'darts' );

			const dartsBlock = authenticatedPage.locator(
				'.block-editor-inserter__panel-content button:has-text("Darts Score Card")'
			);
			await expect( dartsBlock ).toBeVisible();
		} );

		test( 'phase 10 block is available in block inserter', async ( { authenticatedPage } ) => {
			await authenticatedPage.goto( '/wp-admin/post-new.php' );
			await authenticatedPage.waitForSelector( '.edit-post-header' );

			const inserterButton = authenticatedPage.locator(
				'button[aria-label="Toggle block inserter"]'
			);
			await inserterButton.click();

			const searchInput = authenticatedPage.locator(
				'.block-editor-inserter__search input'
			);
			await searchInput.fill( 'phase' );

			const phase10Block = authenticatedPage.locator(
				'.block-editor-inserter__panel-content button:has-text("Phase 10 Score Card")'
			);
			await expect( phase10Block ).toBeVisible();
		} );

		test( 'pool block is available in block inserter', async ( { authenticatedPage } ) => {
			await authenticatedPage.goto( '/wp-admin/post-new.php' );
			await authenticatedPage.waitForSelector( '.edit-post-header' );

			const inserterButton = authenticatedPage.locator(
				'button[aria-label="Toggle block inserter"]'
			);
			await inserterButton.click();

			const searchInput = authenticatedPage.locator(
				'.block-editor-inserter__search input'
			);
			await searchInput.fill( 'pool' );

			const poolBlock = authenticatedPage.locator(
				'.block-editor-inserter__panel-content button:has-text("Pool Score Card")'
			);
			await expect( poolBlock ).toBeVisible();
		} );
	} );
} );
