/**
 * Frontend JavaScript for Apermo Score Cards
 *
 * Handles score submission on the frontend (not in block editor).
 */

import DartsScoreForm from './darts/DartsScoreForm';

/**
 * Initialize all score card forms on the page.
 */
function init() {
	// Initialize Darts blocks
	const dartsBlocks = document.querySelectorAll( '.asc-darts[data-can-manage="true"]' );
	dartsBlocks.forEach( ( block ) => {
		const formContainer = block.querySelector( '.asc-darts-form-container' );
		const editBtn = block.querySelector( '.asc-darts__edit-btn' );
		const duplicateBtn = block.querySelector( '.asc-darts__duplicate-btn' );

		if ( formContainer && ! formContainer.hidden ) {
			// Form is visible (no existing game) - initialize immediately
			new DartsScoreForm( formContainer, block.dataset );
		} else if ( formContainer && editBtn ) {
			// Form is hidden (existing game) - initialize on edit button click
			editBtn.addEventListener( 'click', () => {
				editBtn.hidden = true;
				if ( duplicateBtn ) {
					duplicateBtn.hidden = true;
				}
				formContainer.hidden = false;
				new DartsScoreForm( formContainer, block.dataset );
			} );
		}

		// Handle duplicate button
		if ( duplicateBtn ) {
			duplicateBtn.addEventListener( 'click', () => {
				duplicateBlock( block.dataset.postId, block.dataset.blockId, duplicateBtn );
			} );
		}
	} );
}

/**
 * Duplicate a block via REST API.
 */
async function duplicateBlock( postId, blockId, button ) {
	const config = window.apermoScoreCards || {};
	const restUrl = config.restUrl || '/wp-json/apermo-score-cards/v1';
	const nonce = config.restNonce;

	const originalText = button.textContent;
	button.disabled = true;
	button.textContent = window.wp?.i18n?.__( 'Adding...', 'apermo-score-cards' ) || 'Adding...';

	try {
		const response = await fetch(
			`${ restUrl }/posts/${ postId }/duplicate-block/${ blockId }`,
			{
				method: 'POST',
				headers: {
					'Content-Type': 'application/json',
					'X-WP-Nonce': nonce,
				},
			}
		);

		if ( ! response.ok ) {
			const error = await response.json().catch( () => ( {} ) );
			throw new Error( error.message || 'Failed to add game' );
		}

		// Reload page to show new block
		window.location.reload();
	} catch ( error ) {
		console.error( 'Failed to duplicate block:', error );
		alert( error.message || 'Failed to add game. Please try again.' );
		button.disabled = false;
		button.textContent = originalText;
	}
}

// Initialize when DOM is ready
if ( document.readyState === 'loading' ) {
	document.addEventListener( 'DOMContentLoaded', init );
} else {
	init();
}
