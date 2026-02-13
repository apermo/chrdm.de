/**
 * Darts Scoring Logic
 *
 * Winner determination:
 * 1. Players with final score of 0 (finished) win
 * 2. If multiple players finished, the one with lowest finishedRound wins
 * 3. If tied on rounds (or no rounds specified), it's a draw
 * 4. If no one finished, lowest remaining score wins
 */

/**
 * Determine the winner(s) of a darts game.
 *
 * @param {Object} scores - Object of player scores { playerId: { finalScore, finishedRound } }
 * @return {number[]} Array of winner player IDs (multiple for draws)
 */
export function determineWinners( scores ) {
	if ( ! scores || Object.keys( scores ).length === 0 ) {
		return [];
	}

	const entries = Object.entries( scores ).map( ( [ id, data ] ) => ( {
		id: parseInt( id, 10 ),
		finalScore: data.finalScore,
		finishedRound: data.finishedRound,
	} ) );

	// Find players who finished (score = 0)
	const finishedPlayers = entries.filter( ( p ) => p.finalScore === 0 );

	if ( finishedPlayers.length > 0 ) {
		// Find the lowest round among finished players
		const playersWithRounds = finishedPlayers.filter(
			( p ) => p.finishedRound != null
		);

		if ( playersWithRounds.length > 0 ) {
			const lowestRound = Math.min(
				...playersWithRounds.map( ( p ) => p.finishedRound )
			);
			const winners = playersWithRounds.filter(
				( p ) => p.finishedRound === lowestRound
			);
			return winners.map( ( p ) => p.id );
		}

		// No rounds specified, all finished players are winners (draw)
		return finishedPlayers.map( ( p ) => p.id );
	}

	// No one finished - lowest remaining score wins
	const lowestScore = Math.min( ...entries.map( ( p ) => p.finalScore ) );
	const winners = entries.filter( ( p ) => p.finalScore === lowestScore );

	return winners.map( ( p ) => p.id );
}

/**
 * Check if a score is valid.
 *
 * @param {number} score    - The score to validate
 * @param {number} maxScore - Maximum allowed score (starting score)
 * @return {boolean} True if valid
 */
export function isValidScore( score, maxScore = 501 ) {
	return Number.isInteger( score ) && score >= 0 && score <= maxScore;
}

/**
 * Get the placement/rank for each player.
 *
 * @param {Object} scores - Object of player scores
 * @return {Object} Object mapping playerId to rank (1 = first place)
 */
export function getPlayerRankings( scores ) {
	if ( ! scores || Object.keys( scores ).length === 0 ) {
		return {};
	}

	const entries = Object.entries( scores ).map( ( [ id, data ] ) => ( {
		id: parseInt( id, 10 ),
		finalScore: data.finalScore,
		finishedRound: data.finishedRound,
	} ) );

	// Sort by: finished first, then by round, then by remaining score
	entries.sort( ( a, b ) => {
		// Finished players come first
		if ( a.finalScore === 0 && b.finalScore !== 0 ) {
			return -1;
		}
		if ( b.finalScore === 0 && a.finalScore !== 0 ) {
			return 1;
		}

		// Among finished, sort by round
		if ( a.finalScore === 0 && b.finalScore === 0 ) {
			const roundA = a.finishedRound ?? Infinity;
			const roundB = b.finishedRound ?? Infinity;
			if ( roundA !== roundB ) {
				return roundA - roundB;
			}
		}

		// Sort by remaining score
		return a.finalScore - b.finalScore;
	} );

	const rankings = {};
	let currentRank = 1;
	let previousScore = null;
	let previousRound = null;

	entries.forEach( ( entry, index ) => {
		// Check if tied with previous player
		const isTied =
			entry.finalScore === previousScore &&
			( entry.finishedRound ?? null ) === previousRound;

		if ( ! isTied && index > 0 ) {
			currentRank = index + 1;
		}

		rankings[ entry.id ] = currentRank;
		previousScore = entry.finalScore;
		previousRound = entry.finishedRound ?? null;
	} );

	return rankings;
}
