/**
 * Score Table Component
 *
 * Displays the score table for a game with players and rounds.
 */

import { useSelect } from '@wordpress/data';
import { __ } from '@wordpress/i18n';
import { STORE_NAME } from '../stores';

export default function ScoreTable( {
	playerIds,
	rounds,
	renderRoundCell,
	renderTotalCell,
	calculateTotal,
} ) {
	const players = useSelect(
		( select ) => {
			return select( STORE_NAME ).getPlayersByIds( playerIds );
		},
		[ playerIds ]
	);

	if ( ! players.length ) {
		return null;
	}

	return (
		<div className="asc-score-table-wrapper">
			<table className="asc-score-table">
				<thead>
					<tr>
						<th className="asc-score-table__round-header">
							{ __( 'Round', 'apermo-score-cards' ) }
						</th>
						{ players.map( ( player ) => (
							<th
								key={ player.id }
								className="asc-score-table__player-header"
							>
								<span className="asc-score-table__player">
									{ player.avatarUrl && (
										<img
											src={ player.avatarUrl }
											alt=""
											className="asc-score-table__avatar"
										/>
									) }
									<span className="asc-score-table__name">
										{ player.name }
									</span>
								</span>
							</th>
						) ) }
					</tr>
				</thead>
				<tbody>
					{ rounds.map( ( round, roundIndex ) => (
						<tr
							key={ roundIndex }
							className="asc-score-table__round-row"
						>
							<td className="asc-score-table__round-number">
								{ roundIndex + 1 }
							</td>
							{ players.map( ( player ) => (
								<td
									key={ player.id }
									className="asc-score-table__cell"
								>
									{ renderRoundCell
										? renderRoundCell(
												round,
												player.id,
												roundIndex
										  )
										: round[ player.id ] ?? '-' }
								</td>
							) ) }
						</tr>
					) ) }
				</tbody>
				<tfoot>
					<tr className="asc-score-table__total-row">
						<td className="asc-score-table__total-label">
							{ __( 'Total', 'apermo-score-cards' ) }
						</td>
						{ players.map( ( player ) => {
							const total = calculateTotal
								? calculateTotal( rounds, player.id )
								: rounds.reduce(
										( sum, round ) =>
											sum +
											( Number( round[ player.id ] ) ||
												0 ),
										0
								  );

							return (
								<td
									key={ player.id }
									className="asc-score-table__total-cell"
								>
									{ renderTotalCell
										? renderTotalCell( total, player.id )
										: total }
								</td>
							);
						} ) }
					</tr>
				</tfoot>
			</table>
		</div>
	);
}
