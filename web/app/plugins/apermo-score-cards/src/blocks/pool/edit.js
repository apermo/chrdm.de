/**
 * Pool Billiard - Editor Component
 *
 * Backend handles player pool selection.
 * Game results are entered on the frontend.
 */

import { __ } from '@wordpress/i18n';
import { useBlockProps, InspectorControls } from '@wordpress/block-editor';
import {
	PanelBody,
	TextControl,
	Placeholder,
	Notice,
	Spinner,
} from '@wordpress/components';
import { useSelect, useDispatch } from '@wordpress/data';
import { useEffect } from '@wordpress/element';
import { v4 as uuidv4 } from 'uuid';

import { STORE_NAME } from '../../stores';
import { PlayerSelector } from '../../components';
import PoolStandings from './components/PoolStandings';

export default function Edit( { attributes, setAttributes, context } ) {
	const { blockId, playerIds, customTitle } = attributes;
	const postId = context.postId;
	const blockProps = useBlockProps();

	// Generate block ID if not set
	useEffect( () => {
		if ( ! blockId ) {
			setAttributes( { blockId: uuidv4() } );
		}
	}, [ blockId, setAttributes ] );

	// Fetch players and game data
	const { players, isLoading, game } = useSelect(
		( select ) => {
			const store = select( STORE_NAME );
			store.getPlayers();
			return {
				players: store.getPlayersByIds( playerIds ),
				isLoading: ! store.arePlayersLoaded(),
				game: blockId ? store.getGame( postId, blockId ) : null,
			};
		},
		[ playerIds, postId, blockId ]
	);

	const { fetchGame } = useDispatch( STORE_NAME );

	// Fetch game data on mount
	useEffect( () => {
		if ( postId && blockId ) {
			fetchGame( postId, blockId );
		}
	}, [ postId, blockId, fetchGame ] );

	const hasPlayers = playerIds.length >= 2;
	const hasGames = game?.games?.length > 0;

	return (
		<div { ...blockProps }>
			<InspectorControls>
				<PanelBody
					title={ __( 'Game Settings', 'apermo-score-cards' ) }
				>
					<TextControl
						label={ __( 'Custom Title', 'apermo-score-cards' ) }
						value={ customTitle }
						onChange={ ( value ) =>
							setAttributes( { customTitle: value } )
						}
						placeholder={ __(
							'Pool Billiard',
							'apermo-score-cards'
						) }
						help={ __(
							'Leave empty to use auto-generated title.',
							'apermo-score-cards'
						) }
					/>
				</PanelBody>
				<PanelBody
					title={ __( 'Players', 'apermo-score-cards' ) }
					initialOpen={ true }
				>
					<PlayerSelector
						selectedPlayerIds={ playerIds }
						onChange={ ( newPlayerIds ) =>
							setAttributes( { playerIds: newPlayerIds } )
						}
						minPlayers={ 2 }
						maxPlayers={ 8 }
						disabled={ hasGames }
					/>
					{ hasGames && (
						<p className="components-base-control__help">
							{ __(
								'Cannot change players after games started.',
								'apermo-score-cards'
							) }
						</p>
					) }
				</PanelBody>
			</InspectorControls>

			{ isLoading ? (
				<Placeholder
					icon="awards"
					label={ __( 'Pool Billiard', 'apermo-score-cards' ) }
				>
					<Spinner />
				</Placeholder>
			) : ! hasPlayers ? (
				<Placeholder
					icon="awards"
					label={ __( 'Pool Billiard', 'apermo-score-cards' ) }
					instructions={ __(
						'Select at least 2 players from the sidebar to start.',
						'apermo-score-cards'
					) }
				/>
			) : (
				<div className="asc-pool">
					<div className="asc-pool__header">
						<h3 className="asc-pool__title">
							{ customTitle ||
								__( 'Pool Billiard', 'apermo-score-cards' ) }
						</h3>
						{ game?.status === 'completed' && (
							<span className="asc-pool__status asc-pool__status--completed">
								{ __( 'Completed', 'apermo-score-cards' ) }
							</span>
						) }
					</div>

					{ hasGames ? (
						<PoolStandings
							games={ game.games }
							players={ players }
						/>
					) : (
						<div className="asc-pool__pending">
							<Notice status="info" isDismissible={ false }>
								{ __(
									'Players selected. Save/publish the post, then add games on the frontend.',
									'apermo-score-cards'
								) }
							</Notice>
							<table className="asc-pool-standings__table">
								<thead>
									<tr>
										<th>
											{ __(
												'Player',
												'apermo-score-cards'
											) }
										</th>
										<th>
											{ __( 'W', 'apermo-score-cards' ) }
										</th>
										<th>
											{ __( 'L', 'apermo-score-cards' ) }
										</th>
									</tr>
								</thead>
								<tbody>
									{ players.map( ( player ) => (
										<tr key={ player.id }>
											<td className="asc-pool-standings__player">
												{ player.avatarUrl && (
													<img
														src={ player.avatarUrl }
														alt=""
														className="asc-pool-standings__avatar"
													/>
												) }
												<span>{ player.name }</span>
											</td>
											<td>0</td>
											<td>0</td>
										</tr>
									) ) }
								</tbody>
							</table>
						</div>
					) }
				</div>
			) }
		</div>
	);
}
