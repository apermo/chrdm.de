/**
 * Wizard Score Display Component (Editor)
 *
 * Displays the current game state in the block editor.
 */

import { __ } from '@wordpress/i18n';

/**
 * Calculate score for a single round.
 *
 * @param {number} bid Tricks bid.
 * @param {number} won Tricks won.
 * @return {number} Round score.
 */
function calculateRoundScore( bid, won ) {
	if ( bid === won ) {
		return 20 + won * 10;
	}
	return -10 * Math.abs( bid - won );
}

/**
 * Calculate running totals for a player.
 *
 * @param {Array}  rounds   All rounds data.
 * @param {number} playerId Player ID.
 * @return {Array} Array of running totals per round.
 */
function calculateRunningTotals( rounds, playerId ) {
	let total = 0;
	return rounds.map( ( round ) => {
		const data = round[ playerId ];
		if ( data && typeof data.bid === 'number' && typeof data.won === 'number' ) {
			total += calculateRoundScore( data.bid, data.won );
		}
		return total;
	} );
}

export default function WizardScoreDisplay( { game, players, totalRounds } ) {
	const rounds = game.rounds || [];
	const currentRound = rounds.length;

	// Calculate final scores
	const finalScores = {};
	players.forEach( ( player ) => {
		const totals = calculateRunningTotals( rounds, player.id );
		finalScores[ player.id ] = totals.length > 0 ? totals[ totals.length - 1 ] : 0;
	} );

	// Sort players by score (descending)
	const sortedPlayers = [ ...players ].sort(
		( a, b ) => finalScores[ b.id ] - finalScores[ a.id ]
	);

	const medals = { 1: '🥇', 2: '🥈', 3: '🥉' };

	return (
		<div className="asc-wizard-display">
			<p className="asc-wizard-display__progress">
				{ __( 'Round', 'apermo-score-cards' ) } { currentRound } / { totalRounds }
			</p>

			<div className="asc-wizard-display__table-wrapper">
				<table className="asc-wizard-display__table">
					<thead>
						<tr>
							<th className="asc-wizard-display__rank-col">#</th>
							<th className="asc-wizard-display__player-col">
								{ __( 'Player', 'apermo-score-cards' ) }
							</th>
							<th className="asc-wizard-display__score-col">
								{ __( 'Score', 'apermo-score-cards' ) }
							</th>
						</tr>
					</thead>
					<tbody>
						{ sortedPlayers.map( ( player, index ) => {
							const position = index + 1;
							const medal = medals[ position ];
							const score = finalScores[ player.id ];

							return (
								<tr
									key={ player.id }
									className={ `asc-wizard-display__row ${
										position <= 3 ? `asc-wizard-display__row--position-${ position }` : ''
									}` }
								>
									<td className="asc-wizard-display__rank">
										{ medal || position }
									</td>
									<td className="asc-wizard-display__player">
										{ player.avatarUrl && (
											<img
												src={ player.avatarUrl }
												alt=""
												className="asc-wizard-display__avatar"
											/>
										) }
										<span>{ player.name }</span>
									</td>
									<td className="asc-wizard-display__score">
										<strong>{ score }</strong>
									</td>
								</tr>
							);
						} ) }
					</tbody>
				</table>
			</div>

			<p className="asc-wizard-display__edit-hint">
				{ __( 'Edit scores on the frontend.', 'apermo-score-cards' ) }
			</p>
		</div>
	);
}
