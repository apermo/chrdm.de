/**
 * Shared API utilities for Score Card forms.
 *
 * Provides consistent REST API access across all game form components.
 */

/**
 * Get API configuration from the global window object.
 *
 * @return {Object} Configuration with restUrl and restNonce.
 */
export function getApiConfig() {
	const config = window.apermoScoreCards || {};
	return {
		restUrl: config.restUrl || '/wp-json/apermo-score-cards/v1',
		restNonce: config.restNonce || '',
	};
}

/**
 * Make an API request with authentication.
 *
 * @param {string} endpoint       REST API endpoint (relative to base URL).
 * @param {Object} options        Fetch options.
 * @param {string} options.method HTTP method (GET, POST, PUT, DELETE).
 * @param {Object} options.body   Request body (will be JSON stringified).
 * @return {Promise<Object>}   Response data.
 * @throws {Error}             If the request fails.
 */
export async function apiRequest( endpoint, options = {} ) {
	const { restUrl, restNonce } = getApiConfig();

	const fetchOptions = {
		method: options.method || 'GET',
		headers: {
			'Content-Type': 'application/json',
			'X-WP-Nonce': restNonce,
		},
	};

	if ( options.body ) {
		fetchOptions.body = JSON.stringify( options.body );
	}

	const response = await fetch( `${ restUrl }${ endpoint }`, fetchOptions );

	if ( ! response.ok ) {
		const error = await response.json().catch( () => ( {} ) );
		throw new Error( error.message || 'Request failed' );
	}

	return response.json();
}

/**
 * Save a complete game.
 *
 * @param {number} postId   Post ID containing the block.
 * @param {string} blockId  Block ID.
 * @param {Object} gameData Game data to save.
 * @return {Promise<Object>} Saved game data.
 */
export async function saveGame( postId, blockId, gameData ) {
	return apiRequest( `/posts/${ postId }/games/${ blockId }`, {
		method: 'POST',
		body: gameData,
	} );
}

/**
 * Add a round to an existing game.
 *
 * @param {number} postId    Post ID containing the block.
 * @param {string} blockId   Block ID.
 * @param {Object} roundData Round data to add.
 * @return {Promise<Object>} Updated game data.
 */
export async function addRound( postId, blockId, roundData ) {
	return apiRequest( `/posts/${ postId }/games/${ blockId }/rounds`, {
		method: 'POST',
		body: roundData,
	} );
}

/**
 * Update an existing round.
 *
 * @param {number} postId    Post ID containing the block.
 * @param {string} blockId   Block ID.
 * @param {number} index     Round index to update.
 * @param {Object} roundData Updated round data.
 * @return {Promise<Object>} Updated game data.
 */
export async function updateRound( postId, blockId, index, roundData ) {
	return apiRequest(
		`/posts/${ postId }/games/${ blockId }/rounds/${ index }`,
		{
			method: 'PUT',
			body: roundData,
		}
	);
}

/**
 * Complete a game.
 *
 * @param {number} postId  Post ID containing the block.
 * @param {string} blockId Block ID.
 * @return {Promise<Object>} Completed game data.
 */
export async function completeGame( postId, blockId ) {
	return apiRequest( `/posts/${ postId }/games/${ blockId }/complete`, {
		method: 'POST',
	} );
}

/**
 * Delete a game.
 *
 * @param {number} postId  Post ID containing the block.
 * @param {string} blockId Block ID.
 * @return {Promise<Object>} Response data.
 */
export async function deleteGame( postId, blockId ) {
	return apiRequest( `/posts/${ postId }/games/${ blockId }`, {
		method: 'DELETE',
	} );
}

/**
 * Reset/start a new game (clears existing data).
 *
 * @param {number}   postId    Post ID containing the block.
 * @param {string}   blockId   Block ID.
 * @param {string}   gameType  Type of game (wizard, phase10, darts, pool).
 * @param {number[]} playerIds Array of player IDs.
 * @return {Promise<Object>}   New game data.
 */
export async function resetGame( postId, blockId, gameType, playerIds ) {
	return apiRequest( `/posts/${ postId }/games/${ blockId }`, {
		method: 'POST',
		body: {
			gameType,
			playerIds,
			status: 'pending',
			rounds: [],
			games: [],
			scores: {},
			finalScores: {},
			positions: {},
		},
	} );
}

/**
 * Update player list for a block.
 *
 * @param {number}   postId    Post ID containing the block.
 * @param {string}   blockId   Block ID.
 * @param {number[]} playerIds Array of player IDs.
 * @return {Promise<Object>}   Response data.
 */
export async function updateBlockPlayers( postId, blockId, playerIds ) {
	return apiRequest( `/posts/${ postId }/blocks/${ blockId }/players`, {
		method: 'POST',
		body: { playerIds },
	} );
}

/**
 * Duplicate a block.
 *
 * @param {number} postId  Post ID containing the block.
 * @param {string} blockId Block ID to duplicate.
 * @return {Promise<Object>} New block data.
 */
export async function duplicateBlock( postId, blockId ) {
	return apiRequest( `/posts/${ postId }/duplicate-block/${ blockId }`, {
		method: 'POST',
	} );
}
