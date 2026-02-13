/**
 * Phase 10 Score Card - Editor Component
 *
 * Backend handles player selection. Score input happens on the frontend.
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

	const hasPlayers = playerIds.length >= 2 && playerIds.length <= 6;
	const hasGame = !! game;

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
						placeholder={ __( 'Phase 10', 'apermo-score-cards' ) }
						help={ __(
							'Leave empty to use default title.',
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
						maxPlayers={ 6 }
						disabled={ hasGame }
					/>
					{ hasGame && (
						<p className="components-base-control__help">
							{ __(
								'Cannot change players after game started.',
								'apermo-score-cards'
							) }
						</p>
					) }
				</PanelBody>
			</InspectorControls>

			{ isLoading ? (
				<Placeholder
					icon="editor-ol"
					label={ __( 'Phase 10 Score Card', 'apermo-score-cards' ) }
				>
					<Spinner />
				</Placeholder>
			) : ! hasPlayers ? (
				<Placeholder
					icon="editor-ol"
					label={ __( 'Phase 10 Score Card', 'apermo-score-cards' ) }
					instructions={ __(
						'Select 2-6 players from the sidebar to start.',
						'apermo-score-cards'
					) }
				>
					{ playerIds.length > 0 && playerIds.length < 2 && (
						<Notice status="warning" isDismissible={ false }>
							{ __(
								'Phase 10 requires at least 2 players.',
								'apermo-score-cards'
							) }
						</Notice>
					) }
					{ playerIds.length > 6 && (
						<Notice status="warning" isDismissible={ false }>
							{ __(
								'Phase 10 supports maximum 6 players.',
								'apermo-score-cards'
							) }
						</Notice>
					) }
				</Placeholder>
			) : (
				<div className="asc-phase10">
					<div className="asc-phase10__header">
						<h3 className="asc-phase10__title">
							{ customTitle ||
								__( 'Phase 10', 'apermo-score-cards' ) }
						</h3>
						{ game?.status === 'completed' && (
							<span className="asc-phase10__status asc-phase10__status--completed">
								{ __( 'Completed', 'apermo-score-cards' ) }
							</span>
						) }
					</div>

					{ game ? (
						<div className="asc-phase10-display">
							<p className="asc-phase10-display__info">
								{ __(
									'Game in progress. View scores on the frontend.',
									'apermo-score-cards'
								) }
							</p>
						</div>
					) : (
						<div className="asc-phase10__pending">
							<Notice status="info" isDismissible={ false }>
								{ __(
									'Players selected. Save/publish the post, then enter scores on the frontend.',
									'apermo-score-cards'
								) }
							</Notice>
							<table className="asc-phase10-display__table">
								<thead>
									<tr>
										<th>#</th>
										<th>
											{ __(
												'Player',
												'apermo-score-cards'
											) }
										</th>
									</tr>
								</thead>
								<tbody>
									{ players.map( ( player, index ) => (
										<tr key={ player.id }>
											<td>{ index + 1 }</td>
											<td className="asc-phase10-display__player">
												{ player.avatarUrl && (
													<img
														src={ player.avatarUrl }
														alt=""
														className="asc-phase10-display__avatar"
													/>
												) }
												<span>{ player.name }</span>
											</td>
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
