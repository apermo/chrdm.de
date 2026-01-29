/**
 * Darts Score Display Component
 *
 * Displays the final scores and winners.
 */

import { __ } from '@wordpress/i18n';
import { determineWinners } from '../scoring';

export default function DartsScoreDisplay( {
	game,
	players,
	startingScore,
	isEditor = false,
} ) {
	const { scores, winnerIds = [], status } = game;

	// Sort players by finish order (0 scores first, then by round)
	const sortedPlayers = [ ...players ].sort( ( a, b ) => {
		const scoreA = scores?.[ a.id ];
		const scoreB = scores?.[ b.id ];

		if ( ! scoreA || ! scoreB ) return 0;

		// Players with 0 (finished) come first
		if ( scoreA.finalScore === 0 && scoreB.finalScore !== 0 ) return -1;
		if ( scoreB.finalScore === 0 && scoreA.finalScore !== 0 ) return 1;

		// Among finished players, sort by round (lower is better)
		if ( scoreA.finalScore === 0 && scoreB.finalScore === 0 ) {
			const roundA = scoreA.finishedRound || Infinity;
			const roundB = scoreB.finishedRound || Infinity;
			return roundA - roundB;
		}

		// Among unfinished, sort by remaining score (lower is better)
		return scoreA.finalScore - scoreB.finalScore;
	} );

	const actualWinnerIds = winnerIds.length > 0 ? winnerIds : determineWinners( scores || {} );
	const isDraw = actualWinnerIds.length > 1;

	return (
		<div className="asc-darts-display">
			<table className="asc-darts-display__table">
				<thead>
					<tr>
						<th className="asc-darts-display__rank-header">#</th>
						<th>{ __( 'Player', 'apermo-score-cards' ) }</th>
						<th>{ __( 'Remaining', 'apermo-score-cards' ) }</th>
						<th>{ __( 'Round', 'apermo-score-cards' ) }</th>
					</tr>
				</thead>
				<tbody>
					{ sortedPlayers.map( ( player, index ) => {
						const playerScore = scores?.[ player.id ];
						const isWinner = actualWinnerIds.includes( player.id );
						const isFinished = playerScore?.finalScore === 0;

						return (
							<tr
								key={ player.id }
								className={ `asc-darts-display__row ${
									isWinner ? 'asc-darts-display__row--winner' : ''
								} ${ isFinished ? 'asc-darts-display__row--finished' : '' }` }
							>
								<td className="asc-darts-display__rank">
									{ isWinner && (
										<span className="asc-darts-display__trophy">🏆</span>
									) }
									{ index + 1 }
								</td>
								<td className="asc-darts-display__player">
									{ player.avatarUrl && (
										<img
											src={ player.avatarUrl }
											alt=""
											className="asc-darts-display__avatar"
										/>
									) }
									<span className="asc-darts-display__name">
										{ player.name }
										{ isWinner && isDraw && (
											<span className="asc-darts-display__draw-label">
												{ __( '(Draw)', 'apermo-score-cards' ) }
											</span>
										) }
									</span>
								</td>
								<td
									className={ `asc-darts-display__score ${
										isFinished
											? 'asc-darts-display__score--zero'
											: ''
									}` }
								>
									{ playerScore?.finalScore ?? '-' }
								</td>
								<td className="asc-darts-display__round">
									{ playerScore?.finishedRound || '-' }
								</td>
							</tr>
						);
					} ) }
				</tbody>
			</table>

			{ status === 'completed' && actualWinnerIds.length > 0 && (
				<div className="asc-darts-display__winner-banner">
					{ isDraw ? (
						<>
							<span className="asc-darts-display__winner-icon">🎯</span>
							{ __( 'Draw!', 'apermo-score-cards' ) }
						</>
					) : (
						<>
							<span className="asc-darts-display__winner-icon">🎯</span>
							{ sprintf(
								/* translators: %s: winner name */
								__( '%s wins!', 'apermo-score-cards' ),
								players.find( ( p ) => p.id === actualWinnerIds[ 0 ] )?.name ||
									__( 'Unknown', 'apermo-score-cards' )
							) }
						</>
					) }
				</div>
			) }
		</div>
	);
}