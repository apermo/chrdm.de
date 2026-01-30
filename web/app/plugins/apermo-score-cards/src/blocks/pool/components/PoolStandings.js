/**
 * Pool Standings display component
 */

import { __ } from '@wordpress/i18n';

/**
 * Calculate standings from games array.
 * Scoring: 1 point per game + 2 bonus for winning (win = 3pts, loss = 1pt).
 *
 * @param {Array} games   Array of game objects.
 * @param {Array} players Array of player objects.
 * @return {Array} Sorted standings array.
 */
export function calculateStandings( games, players ) {
	// Initialize stats for each player
	const stats = {};
	players.forEach( ( player ) => {
		stats[ player.id ] = {
			playerId: player.id,
			player,
			wins: 0,
			losses: 0,
			points: 0,
			headToHead: {}, // playerId => { wins, losses }
		};
	} );

	// Calculate wins/losses/points from games
	games.forEach( ( game ) => {
		const { player1, player2, winnerId } = game;
		const loserId = winnerId === player1 ? player2 : player1;

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

	// Convert to array and calculate percentages
	const standings = Object.values( stats ).map( ( s ) => ( {
		...s,
		gamesPlayed: s.wins + s.losses,
		winPct: s.wins + s.losses > 0 ? s.wins / ( s.wins + s.losses ) : 0,
	} ) );

	// Sort by: points desc, win% desc, head-to-head, player ID
	standings.sort( ( a, b ) => {
		// 1. Total points (descending)
		if ( a.points !== b.points ) {
			return b.points - a.points;
		}

		// 2. Win percentage (descending)
		if ( a.winPct !== b.winPct ) {
			return b.winPct - a.winPct;
		}

		// 3. Head-to-head
		const h2h = a.headToHead[ b.playerId ];
		if ( h2h ) {
			const diff = h2h.wins - h2h.losses;
			if ( diff !== 0 ) {
				return -diff; // Positive diff means a beat b more
			}
		}

		// 4. Player ID as stable fallback
		return a.playerId - b.playerId;
	} );

	return standings;
}

export default function PoolStandings( { games, players } ) {
	const standings = calculateStandings( games, players );

	return (
		<table className="asc-pool-standings__table">
			<thead>
				<tr>
					<th className="asc-pool-standings__rank-col">#</th>
					<th className="asc-pool-standings__player-col">
						{ __( 'Player', 'apermo-score-cards' ) }
					</th>
					<th>{ __( 'Pts', 'apermo-score-cards' ) }</th>
					<th>{ __( 'W', 'apermo-score-cards' ) }</th>
					<th>{ __( 'L', 'apermo-score-cards' ) }</th>
					<th>{ __( 'Win%', 'apermo-score-cards' ) }</th>
				</tr>
			</thead>
			<tbody>
				{ standings.map( ( standing, index ) => {
					const position = index + 1;
					const medal =
						position === 1
							? '🥇'
							: position === 2
							? '🥈'
							: position === 3
							? '🥉'
							: null;

					return (
						<tr
							key={ standing.playerId }
							className={ `asc-pool-standings__row ${
								position <= 3
									? `asc-pool-standings__row--position-${ position }`
									: ''
							}` }
						>
							<td className="asc-pool-standings__rank">
								{ medal || position }
							</td>
							<td className="asc-pool-standings__player">
								{ standing.player.avatarUrl && (
									<img
										src={ standing.player.avatarUrl }
										alt=""
										className="asc-pool-standings__avatar"
									/>
								) }
								<span>{ standing.player.name }</span>
							</td>
							<td><strong>{ standing.points }</strong></td>
							<td>{ standing.wins }</td>
							<td>{ standing.losses }</td>
							<td>
								{ standing.gamesPlayed > 0
									? `${ Math.round( standing.winPct * 100 ) }%`
									: '-' }
							</td>
						</tr>
					);
				} ) }
			</tbody>
		</table>
	);
}
