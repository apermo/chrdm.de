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

		if ( formContainer && ! formContainer.hidden ) {
			// Form is visible (no existing game) - initialize immediately
			new DartsScoreForm( formContainer, block.dataset );
		} else if ( formContainer && editBtn ) {
			// Form is hidden (existing game) - initialize on edit button click
			editBtn.addEventListener( 'click', () => {
				editBtn.hidden = true;
				formContainer.hidden = false;
				new DartsScoreForm( formContainer, block.dataset );
			} );
		}
	} );
}

// Initialize when DOM is ready
if ( document.readyState === 'loading' ) {
	document.addEventListener( 'DOMContentLoaded', init );
} else {
	init();
}
