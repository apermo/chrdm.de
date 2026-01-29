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
	// Initialize Darts forms
	const dartsBlocks = document.querySelectorAll( '.asc-darts[data-can-manage="true"]' );
	dartsBlocks.forEach( ( block ) => {
		const formContainer = block.querySelector( '.asc-darts-form-container' );
		if ( formContainer ) {
			new DartsScoreForm( formContainer, block.dataset );
		}
	} );
}

// Initialize when DOM is ready
if ( document.readyState === 'loading' ) {
	document.addEventListener( 'DOMContentLoaded', init );
} else {
	init();
}
