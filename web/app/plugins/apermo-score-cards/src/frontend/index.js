/**
 * Frontend JavaScript for Apermo Score Cards
 *
 * Handles score submission on the frontend (not in block editor).
 */

import DartsScoreForm from './darts/DartsScoreForm';
import PoolGameForm from './pool/PoolGameForm';
import WizardRoundForm from './wizard/WizardRoundForm';
import Phase10RoundForm from './phase10/Phase10RoundForm';
import PlayerSelector from './components/PlayerSelector';

/**
 * Initialize all score card forms on the page.
 */
function init() {
	initDartsBlocks();
	initPoolBlocks();
	initWizardBlocks();
	initPhase10Blocks();
}

/**
 * Initialize Darts blocks.
 */
function initDartsBlocks() {
	const dartsBlocks = document.querySelectorAll( '.asc-darts[data-can-manage="true"]' );
	dartsBlocks.forEach( ( block ) => {
		const formContainer = block.querySelector( '.asc-darts-form-container' );
		const editBtn = block.querySelector( '.asc-darts__edit-btn' );
		const duplicateBtn = block.querySelector( '.asc-darts__duplicate-btn' );
		const editPlayersBtn = block.querySelector( '.asc-darts__edit-players-btn' );
		const playerSelectorContainer = block.querySelector( '.asc-player-selector-container' );

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

		// Handle edit players button
		if ( editPlayersBtn && playerSelectorContainer ) {
			editPlayersBtn.addEventListener( 'click', () => {
				editPlayersBtn.hidden = true;
				playerSelectorContainer.hidden = false;

				const playerIds = JSON.parse( block.dataset.playerIds || '[]' );
				new PlayerSelector( playerSelectorContainer, {
					postId: block.dataset.postId,
					blockId: block.dataset.blockId,
					selectedPlayerIds: playerIds,
					minPlayers: 2,
					maxPlayers: 8,
					onSave: () => window.location.reload(),
				} );
			} );
		}
	} );
}

/**
 * Initialize Pool blocks.
 */
function initPoolBlocks() {
	const poolBlocks = document.querySelectorAll( '.asc-pool[data-can-manage="true"]' );
	poolBlocks.forEach( ( block ) => {
		const formContainer = block.querySelector( '.asc-pool-form-container' );
		const addGameBtn = block.querySelector( '.asc-pool__add-game-btn' );
		const editPlayersBtn = block.querySelector( '.asc-pool__edit-players-btn' );
		const completeBtn = block.querySelector( '.asc-pool__complete-btn' );
		const continueBtn = block.querySelector( '.asc-pool__continue-btn' );
		const playerSelectorContainer = block.querySelector( '.asc-player-selector-container' );

		const players = JSON.parse( block.dataset.players || '[]' );
		const gameData = block.dataset.game ? JSON.parse( block.dataset.game ) : null;
		const games = gameData?.games || [];

		// Get locked player IDs from container data attribute
		const lockedPlayerIds = playerSelectorContainer
			? JSON.parse( playerSelectorContainer.dataset.lockedPlayerIds || '[]' )
			: [];

		// Add game button
		if ( addGameBtn && formContainer ) {
			addGameBtn.addEventListener( 'click', () => {
				addGameBtn.hidden = true;
				formContainer.hidden = false;
				new PoolGameForm( formContainer, {
					postId: block.dataset.postId,
					blockId: block.dataset.blockId,
					players,
					games,
					editIndex: null,
					onSave: () => window.location.reload(),
					onCancel: () => {
						formContainer.hidden = true;
						formContainer.innerHTML = '';
						addGameBtn.hidden = false;
					},
				} );
			} );
		}

		// Edit/Delete buttons on individual games
		block.querySelectorAll( '.asc-pool-games__item' ).forEach( ( item ) => {
			const gameIndex = parseInt( item.dataset.gameIndex, 10 );
			const editBtn = item.querySelector( '[data-action="edit"]' );
			const deleteBtn = item.querySelector( '[data-action="delete"]' );

			if ( editBtn ) {
				editBtn.addEventListener( 'click', () => {
					if ( addGameBtn ) {
						addGameBtn.hidden = true;
					}
					formContainer.hidden = false;
					new PoolGameForm( formContainer, {
						postId: block.dataset.postId,
						blockId: block.dataset.blockId,
						players,
						games,
						editIndex: gameIndex,
						onSave: () => window.location.reload(),
						onCancel: () => {
							formContainer.hidden = true;
							formContainer.innerHTML = '';
							if ( addGameBtn ) {
								addGameBtn.hidden = false;
							}
						},
					} );
				} );
			}

			if ( deleteBtn ) {
				deleteBtn.addEventListener( 'click', () => {
					if ( ! confirm( window.wp?.i18n?.__( 'Delete this game?', 'apermo-score-cards' ) || 'Delete this game?' ) ) {
						return;
					}
					deletePoolGame( block.dataset.postId, block.dataset.blockId, players, games, gameIndex );
				} );
			}
		} );

		// Handle edit players button
		if ( editPlayersBtn && playerSelectorContainer ) {
			editPlayersBtn.addEventListener( 'click', () => {
				editPlayersBtn.hidden = true;
				playerSelectorContainer.hidden = false;

				const playerIds = JSON.parse( block.dataset.playerIds || '[]' );
				new PlayerSelector( playerSelectorContainer, {
					postId: block.dataset.postId,
					blockId: block.dataset.blockId,
					selectedPlayerIds: playerIds,
					lockedPlayerIds,
					minPlayers: 2,
					maxPlayers: 8,
					onSave: () => window.location.reload(),
					onCancel: () => {
						editPlayersBtn.hidden = false;
					},
				} );
			} );
		}

		// Handle complete/finish button
		if ( completeBtn ) {
			completeBtn.addEventListener( 'click', () => {
				completePoolSession( block.dataset.postId, block.dataset.blockId, players, games, completeBtn );
			} );
		}

		// Handle continue button
		if ( continueBtn ) {
			continueBtn.addEventListener( 'click', () => {
				continuePoolSession( block.dataset.postId, block.dataset.blockId, players, games, continueBtn );
			} );
		}
	} );
}

/**
 * Delete a pool game.
 */
async function deletePoolGame( postId, blockId, players, games, gameIndex ) {
	const config = window.apermoScoreCards || {};
	const restUrl = config.restUrl || '/wp-json/apermo-score-cards/v1';
	const nonce = config.restNonce;

	const updatedGames = games.filter( ( _, i ) => i !== gameIndex );

	// Recalculate positions
	const positions = calculatePoolPositions( players, updatedGames );

	// Extract player IDs from players array
	const playerIds = players.map( ( p ) => p.id );

	try {
		const response = await fetch(
			`${ restUrl }/posts/${ postId }/games/${ blockId }`,
			{
				method: 'POST',
				headers: {
					'Content-Type': 'application/json',
					'X-WP-Nonce': nonce,
				},
				body: JSON.stringify( {
					gameType: 'pool',
					playerIds,
					games: updatedGames,
					positions,
					status: updatedGames.length > 0 ? 'in_progress' : 'pending',
				} ),
			}
		);

		if ( ! response.ok ) {
			const error = await response.json().catch( () => ( {} ) );
			throw new Error( error.message || 'Failed to delete game' );
		}

		window.location.reload();
	} catch ( error ) {
		console.error( 'Failed to delete pool game:', error );
		alert( error.message || 'Failed to delete game. Please try again.' );
	}
}

/**
 * Complete/finish a pool session.
 * Removes players without games and marks as completed.
 * Stores final scores so future formula changes don't affect past games.
 */
async function completePoolSession( postId, blockId, players, games, button ) {
	const config = window.apermoScoreCards || {};
	const restUrl = config.restUrl || '/wp-json/apermo-score-cards/v1';
	const nonce = config.restNonce;

	const originalText = button.textContent;
	button.disabled = true;
	button.textContent = window.wp?.i18n?.__( 'Finishing...', 'apermo-score-cards' ) || 'Finishing...';

	// Find players who participated in at least one game
	const playersWithGames = new Set();
	games.forEach( ( g ) => {
		playersWithGames.add( g.player1 );
		playersWithGames.add( g.player2 );
	} );

	// Filter to only players with games
	const activePlayers = players.filter( ( p ) => playersWithGames.has( p.id ) );
	const activePlayerIds = activePlayers.map( ( p ) => p.id );

	// Calculate positions and final scores with filtered players
	const { positions, finalScores } = calculatePoolFinalResults( activePlayers, games );

	try {
		// First update block attributes to remove players without games
		// (Must be done before marking as completed, as completed status blocks player updates)
		const playersResponse = await fetch(
			`${ restUrl }/posts/${ postId }/blocks/${ blockId }/players`,
			{
				method: 'POST',
				headers: {
					'Content-Type': 'application/json',
					'X-WP-Nonce': nonce,
				},
				body: JSON.stringify( { playerIds: activePlayerIds } ),
			}
		);

		if ( ! playersResponse.ok ) {
			const error = await playersResponse.json().catch( () => ( {} ) );
			throw new Error( error.message || 'Failed to update players' );
		}

		// Then update the game data with status completed and store final scores
		const response = await fetch(
			`${ restUrl }/posts/${ postId }/games/${ blockId }`,
			{
				method: 'POST',
				headers: {
					'Content-Type': 'application/json',
					'X-WP-Nonce': nonce,
				},
				body: JSON.stringify( {
					gameType: 'pool',
					playerIds: activePlayerIds,
					games,
					positions,
					finalScores,
					status: 'completed',
				} ),
			}
		);

		if ( ! response.ok ) {
			const error = await response.json().catch( () => ( {} ) );
			throw new Error( error.message || 'Failed to complete session' );
		}

		window.location.reload();
	} catch ( error ) {
		console.error( 'Failed to complete pool session:', error );
		alert( error.message || 'Failed to complete session. Please try again.' );
		button.disabled = false;
		button.textContent = originalText;
	}
}

/**
 * Continue a completed pool session.
 * Sets status back to in_progress.
 */
async function continuePoolSession( postId, blockId, players, games, button ) {
	const config = window.apermoScoreCards || {};
	const restUrl = config.restUrl || '/wp-json/apermo-score-cards/v1';
	const nonce = config.restNonce;

	const originalText = button.textContent;
	button.disabled = true;
	button.textContent = window.wp?.i18n?.__( 'Continuing...', 'apermo-score-cards' ) || 'Continuing...';

	const playerIds = players.map( ( p ) => p.id );
	const positions = calculatePoolPositions( players, games );

	try {
		const response = await fetch(
			`${ restUrl }/posts/${ postId }/games/${ blockId }`,
			{
				method: 'POST',
				headers: {
					'Content-Type': 'application/json',
					'X-WP-Nonce': nonce,
				},
				body: JSON.stringify( {
					gameType: 'pool',
					playerIds,
					games,
					positions,
					status: 'in_progress',
				} ),
			}
		);

		if ( ! response.ok ) {
			const error = await response.json().catch( () => ( {} ) );
			throw new Error( error.message || 'Failed to continue session' );
		}

		window.location.reload();
	} catch ( error ) {
		console.error( 'Failed to continue pool session:', error );
		alert( error.message || 'Failed to continue session. Please try again.' );
		button.disabled = false;
		button.textContent = originalText;
	}
}

/**
 * Calculate pool final results including positions and scores.
 * Used when completing a session to store final scores.
 * Scoring: 1 point per game + 2 bonus for winning (win = 3pts, loss = 1pt).
 *
 * @param {Array} players Array of player objects.
 * @param {Array} games   Array of game objects.
 * @return {Object} Object with positions and finalScores.
 */
function calculatePoolFinalResults( players, games ) {
	const stats = {};
	players.forEach( ( p ) => {
		stats[ p.id ] = { wins: 0, losses: 0, points: 0, headToHead: {} };
	} );

	games.forEach( ( g ) => {
		const winnerId = g.winnerId;
		const loserId = g.winnerId === g.player1 ? g.player2 : g.player1;

		if ( stats[ winnerId ] ) {
			stats[ winnerId ].wins++;
			stats[ winnerId ].points += 3;
			if ( ! stats[ winnerId ].headToHead[ loserId ] ) {
				stats[ winnerId ].headToHead[ loserId ] = { wins: 0, losses: 0 };
			}
			stats[ winnerId ].headToHead[ loserId ].wins++;
		}

		if ( stats[ loserId ] ) {
			stats[ loserId ].losses++;
			stats[ loserId ].points += 1;
			if ( ! stats[ loserId ].headToHead[ winnerId ] ) {
				stats[ loserId ].headToHead[ winnerId ] = { wins: 0, losses: 0 };
			}
			stats[ loserId ].headToHead[ winnerId ].losses++;
		}
	} );

	const sorted = Object.entries( stats ).sort( ( [ aId, a ], [ bId, b ] ) => {
		if ( a.points !== b.points ) {
			return b.points - a.points;
		}
		const aTotal = a.wins + a.losses;
		const bTotal = b.wins + b.losses;
		const aPct = aTotal > 0 ? a.wins / aTotal : 0;
		const bPct = bTotal > 0 ? b.wins / bTotal : 0;
		if ( aPct !== bPct ) {
			return bPct - aPct;
		}
		const h2h = a.headToHead[ bId ];
		if ( h2h ) {
			const diff = h2h.wins - h2h.losses;
			if ( diff !== 0 ) {
				return -diff;
			}
		}
		return 0;
	} );

	const positions = {};
	const finalScores = {};
	let currentPos = 1;
	let prevKey = null;

	sorted.forEach( ( [ playerId, s ], index ) => {
		const total = s.wins + s.losses;
		const pct = total > 0 ? Math.round( ( s.wins / total ) * 10000 ) : 0;
		const key = `${ s.points }-${ pct }`;

		if ( key !== prevKey ) {
			currentPos = index + 1;
			prevKey = key;
		}

		positions[ playerId ] = currentPos;
		finalScores[ playerId ] = s.points;
	} );

	return { positions, finalScores };
}

/**
 * Calculate pool positions for evening summary.
 * Scoring: 1 point per game + 2 bonus for winning (win = 3pts, loss = 1pt).
 */
function calculatePoolPositions( players, games ) {
	const stats = {};
	players.forEach( ( p ) => {
		stats[ p.id ] = { wins: 0, losses: 0, points: 0, headToHead: {} };
	} );

	games.forEach( ( g ) => {
		const winnerId = g.winnerId;
		const loserId = g.winnerId === g.player1 ? g.player2 : g.player1;

		if ( stats[ winnerId ] ) {
			stats[ winnerId ].wins++;
			stats[ winnerId ].points += 3; // 1 point for playing + 2 for winning = 3.
			if ( ! stats[ winnerId ].headToHead[ loserId ] ) {
				stats[ winnerId ].headToHead[ loserId ] = { wins: 0, losses: 0 };
			}
			stats[ winnerId ].headToHead[ loserId ].wins++;
		}

		if ( stats[ loserId ] ) {
			stats[ loserId ].losses++;
			stats[ loserId ].points += 1; // 1 point for playing.
			if ( ! stats[ loserId ].headToHead[ winnerId ] ) {
				stats[ loserId ].headToHead[ winnerId ] = { wins: 0, losses: 0 };
			}
			stats[ loserId ].headToHead[ winnerId ].losses++;
		}
	} );

	const sorted = Object.entries( stats ).sort( ( [ aId, a ], [ bId, b ] ) => {
		// 1. Points descending.
		if ( a.points !== b.points ) {
			return b.points - a.points;
		}
		// 2. Win percentage descending.
		const aTotal = a.wins + a.losses;
		const bTotal = b.wins + b.losses;
		const aPct = aTotal > 0 ? a.wins / aTotal : 0;
		const bPct = bTotal > 0 ? b.wins / bTotal : 0;
		if ( aPct !== bPct ) {
			return bPct - aPct;
		}
		// 3. Head-to-head.
		const h2h = a.headToHead[ bId ];
		if ( h2h ) {
			const diff = h2h.wins - h2h.losses;
			if ( diff !== 0 ) {
				return -diff;
			}
		}
		return 0;
	} );

	const positions = {};
	let currentPos = 1;
	let prevKey = null;

	sorted.forEach( ( [ playerId, s ], index ) => {
		const total = s.wins + s.losses;
		const pct = total > 0 ? Math.round( ( s.wins / total ) * 10000 ) : 0;
		const key = `${ s.points }-${ pct }`;

		if ( key !== prevKey ) {
			currentPos = index + 1;
			prevKey = key;
		}

		positions[ playerId ] = currentPos;
	} );

	return positions;
}

/**
 * Initialize Wizard blocks.
 */
function initWizardBlocks() {
	const wizardBlocks = document.querySelectorAll( '.asc-wizard[data-can-manage="true"]' );
	wizardBlocks.forEach( ( block ) => {
		const formContainer = block.querySelector( '.asc-wizard-form-container' );
		const addRoundBtn = block.querySelector( '.asc-wizard__add-round-btn' );
		const editRoundBtns = block.querySelectorAll( '.asc-wizard__edit-round-btn' );
		const completeBtn = block.querySelector( '.asc-wizard__complete-btn' );
		const editPlayersBtn = block.querySelector( '.asc-wizard__edit-players-btn' );
		const playerSelectorContainer = block.querySelector( '.asc-player-selector-container' );

		const players = JSON.parse( block.dataset.players || '[]' );
		const gameData = block.dataset.game ? JSON.parse( block.dataset.game ) : null;
		const rounds = gameData?.rounds || [];
		const totalRounds = parseInt( block.dataset.totalRounds, 10 ) || 15;

		// Add round button - show form for new round
		if ( addRoundBtn && formContainer ) {
			addRoundBtn.addEventListener( 'click', () => {
				addRoundBtn.hidden = true;
				formContainer.hidden = false;
				new WizardRoundForm( formContainer, {
					postId: block.dataset.postId,
					blockId: block.dataset.blockId,
					players,
					rounds,
					totalRounds,
					editRoundIndex: null,
					onSave: () => window.location.reload(),
					onCancel: () => {
						formContainer.hidden = true;
						formContainer.innerHTML = '';
						addRoundBtn.hidden = false;
					},
				} );
			} );
		}

		// Edit round buttons
		editRoundBtns.forEach( ( btn ) => {
			btn.addEventListener( 'click', () => {
				const roundIndex = parseInt( btn.dataset.round, 10 );
				if ( addRoundBtn ) {
					addRoundBtn.hidden = true;
				}
				formContainer.hidden = false;
				new WizardRoundForm( formContainer, {
					postId: block.dataset.postId,
					blockId: block.dataset.blockId,
					players,
					rounds,
					totalRounds,
					editRoundIndex: roundIndex,
					onSave: () => window.location.reload(),
					onCancel: () => {
						formContainer.hidden = true;
						formContainer.innerHTML = '';
						if ( addRoundBtn ) {
							addRoundBtn.hidden = false;
						}
					},
				} );
			} );
		} );

		// Complete game button
		if ( completeBtn ) {
			completeBtn.addEventListener( 'click', () => {
				completeWizardGame( block.dataset.postId, block.dataset.blockId, players, rounds, completeBtn );
			} );
		}

		// No existing game - show form immediately
		if ( formContainer && ! formContainer.hidden && ! gameData ) {
			new WizardRoundForm( formContainer, {
				postId: block.dataset.postId,
				blockId: block.dataset.blockId,
				players,
				rounds: [],
				totalRounds,
				editRoundIndex: null,
				onSave: () => window.location.reload(),
			} );
		}

		// Handle edit players button
		if ( editPlayersBtn && playerSelectorContainer ) {
			editPlayersBtn.addEventListener( 'click', () => {
				editPlayersBtn.hidden = true;
				playerSelectorContainer.hidden = false;

				const playerIds = JSON.parse( block.dataset.playerIds || '[]' );
				new PlayerSelector( playerSelectorContainer, {
					postId: block.dataset.postId,
					blockId: block.dataset.blockId,
					selectedPlayerIds: playerIds,
					minPlayers: 3,
					maxPlayers: 6,
					onSave: () => window.location.reload(),
					onCancel: () => {
						editPlayersBtn.hidden = false;
					},
				} );
			} );
		}
	} );
}

/**
 * Complete a Wizard game.
 */
async function completeWizardGame( postId, blockId, players, rounds, button ) {
	const config = window.apermoScoreCards || {};
	const restUrl = config.restUrl || '/wp-json/apermo-score-cards/v1';
	const nonce = config.restNonce;

	const originalText = button.textContent;
	button.disabled = true;
	button.textContent = window.wp?.i18n?.__( 'Completing...', 'apermo-score-cards' ) || 'Completing...';

	// Calculate final scores
	const finalScores = {};
	const positions = {};

	players.forEach( ( player ) => {
		let total = 0;
		rounds.forEach( ( round ) => {
			const data = round[ player.id ];
			if ( data && typeof data.bid === 'number' && typeof data.won === 'number' ) {
				if ( data.bid === data.won ) {
					total += 20 + data.won * 10;
				} else {
					total -= 10 * Math.abs( data.bid - data.won );
				}
			}
		} );
		finalScores[ player.id ] = total;
	} );

	// Sort and calculate positions
	const sorted = Object.entries( finalScores ).sort( ( a, b ) => b[ 1 ] - a[ 1 ] );
	let currentPos = 1;
	let prevScore = null;

	sorted.forEach( ( [ playerId, score ], index ) => {
		if ( score !== prevScore ) {
			currentPos = index + 1;
			prevScore = score;
		}
		positions[ playerId ] = currentPos;
	} );

	// Find winner(s)
	const winnerIds = sorted.filter( ( [ _, score ] ) => score === sorted[ 0 ][ 1 ] ).map( ( [ id ] ) => parseInt( id, 10 ) );

	const playerIds = players.map( ( p ) => p.id );

	try {
		const response = await fetch(
			`${ restUrl }/posts/${ postId }/games/${ blockId }`,
			{
				method: 'POST',
				headers: {
					'Content-Type': 'application/json',
					'X-WP-Nonce': nonce,
				},
				body: JSON.stringify( {
					gameType: 'wizard',
					playerIds,
					rounds,
					finalScores,
					positions,
					winnerIds,
					status: 'completed',
				} ),
			}
		);

		if ( ! response.ok ) {
			const error = await response.json().catch( () => ( {} ) );
			throw new Error( error.message || 'Failed to complete game' );
		}

		window.location.reload();
	} catch ( error ) {
		console.error( 'Failed to complete wizard game:', error );
		alert( error.message || 'Failed to complete game. Please try again.' );
		button.disabled = false;
		button.textContent = originalText;
	}
}

/**
 * Initialize Phase 10 blocks.
 */
function initPhase10Blocks() {
	const phase10Blocks = document.querySelectorAll( '.asc-phase10[data-can-manage="true"]' );
	phase10Blocks.forEach( ( block ) => {
		const formContainer = block.querySelector( '.asc-phase10-form-container' );
		const addRoundBtn = block.querySelector( '.asc-phase10__add-round-btn' );
		const editRoundBtns = block.querySelectorAll( '.asc-phase10__edit-round-btn' );
		const completeBtn = block.querySelector( '.asc-phase10__complete-btn' );
		const editPlayersBtn = block.querySelector( '.asc-phase10__edit-players-btn' );
		const playerSelectorContainer = block.querySelector( '.asc-player-selector-container' );

		const players = JSON.parse( block.dataset.players || '[]' );
		const gameData = block.dataset.game ? JSON.parse( block.dataset.game ) : null;
		const rounds = gameData?.rounds || [];

		// Add round button - show form for new round
		if ( addRoundBtn && formContainer ) {
			addRoundBtn.addEventListener( 'click', () => {
				addRoundBtn.hidden = true;
				formContainer.hidden = false;
				new Phase10RoundForm( formContainer, {
					postId: block.dataset.postId,
					blockId: block.dataset.blockId,
					players,
					rounds,
					editRoundIndex: null,
					onSave: () => window.location.reload(),
					onCancel: () => {
						formContainer.hidden = true;
						formContainer.innerHTML = '';
						addRoundBtn.hidden = false;
					},
				} );
			} );
		}

		// Edit round buttons
		editRoundBtns.forEach( ( btn ) => {
			btn.addEventListener( 'click', () => {
				const roundIndex = parseInt( btn.dataset.round, 10 );
				if ( addRoundBtn ) {
					addRoundBtn.hidden = true;
				}
				formContainer.hidden = false;
				new Phase10RoundForm( formContainer, {
					postId: block.dataset.postId,
					blockId: block.dataset.blockId,
					players,
					rounds,
					editRoundIndex: roundIndex,
					onSave: () => window.location.reload(),
					onCancel: () => {
						formContainer.hidden = true;
						formContainer.innerHTML = '';
						if ( addRoundBtn ) {
							addRoundBtn.hidden = false;
						}
					},
				} );
			} );
		} );

		// Complete game button
		if ( completeBtn ) {
			completeBtn.addEventListener( 'click', () => {
				completePhase10Game( block.dataset.postId, block.dataset.blockId, players, rounds, completeBtn );
			} );
		}

		// No existing game - show form immediately
		if ( formContainer && ! formContainer.hidden && ! gameData ) {
			new Phase10RoundForm( formContainer, {
				postId: block.dataset.postId,
				blockId: block.dataset.blockId,
				players,
				rounds: [],
				editRoundIndex: null,
				onSave: () => window.location.reload(),
			} );
		}

		// Handle edit players button
		if ( editPlayersBtn && playerSelectorContainer ) {
			editPlayersBtn.addEventListener( 'click', () => {
				editPlayersBtn.hidden = true;
				playerSelectorContainer.hidden = false;

				const playerIds = JSON.parse( block.dataset.playerIds || '[]' );
				new PlayerSelector( playerSelectorContainer, {
					postId: block.dataset.postId,
					blockId: block.dataset.blockId,
					selectedPlayerIds: playerIds,
					minPlayers: 2,
					maxPlayers: 6,
					onSave: () => window.location.reload(),
					onCancel: () => {
						editPlayersBtn.hidden = false;
					},
				} );
			} );
		}
	} );
}

/**
 * Complete a Phase 10 game.
 */
async function completePhase10Game( postId, blockId, players, rounds, button ) {
	const config = window.apermoScoreCards || {};
	const restUrl = config.restUrl || '/wp-json/apermo-score-cards/v1';
	const nonce = config.restNonce;

	const originalText = button.textContent;
	button.disabled = true;
	button.textContent = window.wp?.i18n?.__( 'Completing...', 'apermo-score-cards' ) || 'Completing...';

	// Calculate final scores (sum of all round points - lowest wins)
	const finalScores = {};
	const positions = {};

	players.forEach( ( player ) => {
		let total = 0;
		rounds.forEach( ( round ) => {
			total += round[ player.id ]?.points ?? 0;
		} );
		finalScores[ player.id ] = total;
	} );

	// Sort by score ascending (lowest wins) and calculate positions
	const sorted = Object.entries( finalScores ).sort( ( a, b ) => a[ 1 ] - b[ 1 ] );
	let currentPos = 1;
	let prevScore = null;

	sorted.forEach( ( [ playerId, score ], index ) => {
		if ( score !== prevScore ) {
			currentPos = index + 1;
			prevScore = score;
		}
		positions[ playerId ] = currentPos;
	} );

	// Find winner(s) - lowest score
	const winnerIds = sorted.filter( ( [ _, score ] ) => score === sorted[ 0 ][ 1 ] ).map( ( [ id ] ) => parseInt( id, 10 ) );

	const playerIds = players.map( ( p ) => p.id );

	try {
		const response = await fetch(
			`${ restUrl }/posts/${ postId }/games/${ blockId }`,
			{
				method: 'POST',
				headers: {
					'Content-Type': 'application/json',
					'X-WP-Nonce': nonce,
				},
				body: JSON.stringify( {
					gameType: 'phase10',
					playerIds,
					rounds,
					finalScores,
					positions,
					winnerIds,
					status: 'completed',
				} ),
			}
		);

		if ( ! response.ok ) {
			const error = await response.json().catch( () => ( {} ) );
			throw new Error( error.message || 'Failed to complete game' );
		}

		window.location.reload();
	} catch ( error ) {
		console.error( 'Failed to complete Phase 10 game:', error );
		alert( error.message || 'Failed to complete game. Please try again.' );
		button.disabled = false;
		button.textContent = originalText;
	}
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
