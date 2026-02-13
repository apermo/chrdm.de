/**
 * Darts Score Display Component
 *
 * Displays the final scores and winners in the editor.
 */

import { __, sprintf } from '@wordpress/i18n';
import { determineWinners } from '../scoring';

export default function DartsScoreDisplay( {
	game,
	players,
	canEdit = false,
} ) {
	const { scores, winnerIds = [], status, finishedRound } = game;

	// Sort players by position (from game.positions if available, otherwise by score).
	const positions = game.positions || {};
	const sortedPlayers = [ ...players ].sort( ( a, b ) => {
		const posA = positions[ a.id ] ?? 999;
		const posB = positions[ b.id ] ?? 999;
		return posA - posB;
	} );

	const actualWinnerIds =
		winnerIds.length > 0 ? winnerIds : determineWinners( scores || {} );
	const medals = { 1: '🥇', 2: '🥈', 3: '🥉' };

	return (
		<div className="asc-darts-display">
			<table className="asc-darts-display__table">
				<thead>
					<tr>
						<th className="asc-darts-display__rank-header">#</th>
						<th>{ __( 'Player', 'apermo-score-cards' ) }</th>
						<th>{ __( 'Remaining', 'apermo-score-cards' ) }</th>
					</tr>
				</thead>
				<tbody>
					{ sortedPlayers.map( ( player ) => {
						const playerScore = scores?.[ player.id ];
						const position = positions[ player.id ] ?? 0;
						const medal = medals[ position ] || '';
						const isFinished = playerScore?.finalScore === 0;

						const rowClasses = [ 'asc-darts-display__row' ];
						if ( position <= 3 ) {
							rowClasses.push( 'asc-darts-display__row--podium' );
							rowClasses.push(
								`asc-darts-display__row--position-${ position }`
							);
						}
						if ( isFinished ) {
							rowClasses.push(
								'asc-darts-display__row--finished'
							);
						}

						return (
							<tr
								key={ player.id }
								className={ rowClasses.join( ' ' ) }
							>
								<td className="asc-darts-display__rank">
									{ medal ? (
										<span className="asc-darts-display__medal">
											{ medal }
										</span>
									) : (
										position
									) }
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
							</tr>
						);
					} ) }
				</tbody>
			</table>

			{ finishedRound && (
				<p className="asc-darts-display__round-info">
					{ sprintf(
						/* translators: %d: round number */
						__( 'Finished after round %d', 'apermo-score-cards' ),
						finishedRound
					) }
				</p>
			) }

			<p className="asc-darts-display__edit-hint">
				{ canEdit
					? __(
							'Edit results on the frontend.',
							'apermo-score-cards'
					  )
					: __(
							'Results can no longer be edited.',
							'apermo-score-cards'
					  ) }
			</p>
		</div>
	);
}
