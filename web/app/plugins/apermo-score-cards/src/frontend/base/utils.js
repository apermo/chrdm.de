/**
 * Shared utility functions for Score Card forms.
 *
 * Common helpers used across all game form components.
 */

/**
 * Escape HTML to prevent XSS.
 *
 * @param {string} text Text to escape.
 * @return {string}     HTML-safe text.
 */
export function escapeHtml( text ) {
	if ( typeof text !== 'string' ) {
		return '';
	}
	const div = document.createElement( 'div' );
	div.textContent = text;
	return div.innerHTML;
}

/**
 * Translation helper.
 * Uses WordPress i18n if available, otherwise returns the original string.
 *
 * @param {string} text   Text to translate.
 * @param {string} domain Text domain (default: 'apermo-score-cards').
 * @return {string}       Translated text.
 */
export function __( text, domain = 'apermo-score-cards' ) {
	return window.wp?.i18n?.__( text, domain ) || text;
}

/**
 * Sprintf-style string formatting.
 * Replaces %s with provided arguments.
 *
 * @param {string}    format Format string with %s placeholders.
 * @param {...string} args   Values to replace placeholders.
 * @return {string}          Formatted string.
 */
export function sprintf( format, ...args ) {
	let i = 0;
	return format.replace( /%s/g, () => args[ i++ ] ?? '' );
}

/**
 * Generate a unique ID for form elements.
 *
 * @param {string} prefix Optional prefix for the ID.
 * @return {string}       Unique ID string.
 */
export function uniqueId( prefix = 'asc' ) {
	return `${ prefix }-${ Math.random().toString( 36 ).substr( 2, 9 ) }`;
}

/**
 * Debounce a function.
 *
 * @param {Function} func Function to debounce.
 * @param {number}   wait Wait time in milliseconds.
 * @return {Function}      Debounced function.
 */
export function debounce( func, wait ) {
	let timeout;
	return function executedFunction( ...args ) {
		const later = () => {
			clearTimeout( timeout );
			func( ...args );
		};
		clearTimeout( timeout );
		timeout = setTimeout( later, wait );
	};
}

/**
 * Parse a data attribute as JSON safely.
 *
 * @param {string} value        JSON string or empty.
 * @param {*}      defaultValue Default value if parsing fails.
 * @return {*}     Parsed value or default.
 */
export function parseJsonAttr( value, defaultValue = null ) {
	if ( ! value ) {
		return defaultValue;
	}
	try {
		return JSON.parse( value );
	} catch {
		return defaultValue;
	}
}

/**
 * Medal emoji constants.
 */
export const MEDALS = {
	1: '🥇',
	2: '🥈',
	3: '🥉',
};

/**
 * Get medal emoji for a position.
 *
 * @param {number} position Player position (1, 2, or 3).
 * @return {string}         Medal emoji or position number.
 */
export function getMedal( position ) {
	return MEDALS[ position ] || position.toString();
}

/**
 * Calculate positions from scores (higher is better).
 * Handles ties by giving the same position to equal scores.
 *
 * @param {Object} scores Object mapping player ID to score.
 * @return {Object}       Object mapping player ID to position.
 */
export function calculatePositionsHigherWins( scores ) {
	const entries = Object.entries( scores );
	entries.sort( ( a, b ) => b[ 1 ] - a[ 1 ] );

	const positions = {};
	let currentPosition = 1;
	let previousScore = null;

	entries.forEach( ( [ id, score ], index ) => {
		if ( score !== previousScore ) {
			currentPosition = index + 1;
			previousScore = score;
		}
		positions[ id ] = currentPosition;
	} );

	return positions;
}

/**
 * Calculate positions from scores (lower is better).
 * Handles ties by giving the same position to equal scores.
 *
 * @param {Object} scores Object mapping player ID to score.
 * @return {Object}       Object mapping player ID to position.
 */
export function calculatePositionsLowerWins( scores ) {
	const entries = Object.entries( scores );
	entries.sort( ( a, b ) => a[ 1 ] - b[ 1 ] );

	const positions = {};
	let currentPosition = 1;
	let previousScore = null;

	entries.forEach( ( [ id, score ], index ) => {
		if ( score !== previousScore ) {
			currentPosition = index + 1;
			previousScore = score;
		}
		positions[ id ] = currentPosition;
	} );

	return positions;
}

/**
 * Get winner IDs from positions (all players in position 1).
 *
 * @param {Object} positions Object mapping player ID to position.
 * @return {number[]}        Array of winner player IDs.
 */
export function getWinnerIds( positions ) {
	return Object.entries( positions )
		.filter( ( [ , pos ] ) => pos === 1 )
		.map( ( [ id ] ) => parseInt( id, 10 ) );
}

/**
 * Format a player display with avatar and name.
 *
 * @param {Object} player    Player object with id, name, avatarUrl.
 * @param {string} className Optional CSS class name for wrapper.
 * @return {string}          HTML string for player display.
 */
export function formatPlayerHtml( player, className = '' ) {
	const avatar = player.avatarUrl
		? `<img src="${ escapeHtml(
				player.avatarUrl
		  ) }" alt="" class="${ className }__avatar" />`
		: '';
	return `
		<div class="${ className }__player">
			${ avatar }
			<span class="${ className }__name">${ escapeHtml( player.name ) }</span>
		</div>
	`;
}

/**
 * Create HTML for action buttons.
 *
 * @param {Object}  options            Button options.
 * @param {string}  options.submitText Text for submit button.
 * @param {string}  options.cancelText Text for cancel button (optional).
 * @param {string}  options.className  CSS class prefix.
 * @param {boolean} options.showCancel Whether to show cancel button.
 * @return {string}                     HTML string for buttons.
 */
export function formatActionButtonsHtml( options = {} ) {
	const {
		submitText = __( 'Save' ),
		cancelText = __( 'Cancel' ),
		className = 'asc-form',
		showCancel = true,
	} = options;

	let html = `<button type="submit" class="${ className }__submit">${ escapeHtml(
		submitText
	) }</button>`;

	if ( showCancel ) {
		html += `<button type="button" class="${ className }__cancel">${ escapeHtml(
			cancelText
		) }</button>`;
	}

	return html;
}
