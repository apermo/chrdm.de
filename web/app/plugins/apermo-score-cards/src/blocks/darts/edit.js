/**
 * Darts Score Card - Editor Component
 *
 * Backend only handles player selection and game settings.
 * Score input happens on the frontend.
 */

import { __ } from '@wordpress/i18n';
import { useBlockProps, InspectorControls } from '@wordpress/block-editor';
import {
	PanelBody,
	SelectControl,
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
import DartsScoreDisplay from './components/DartsScoreDisplay';

const STARTING_SCORE_OPTIONS = [
	{ label: '301', value: 301 },
	{ label: '501', value: 501 },
	{ label: '701', value: 701 },
	{ label: '901', value: 901 },
];

export default function Edit( { attributes, setAttributes, context } ) {
	const { blockId, playerIds, startingScore, customTitle } = attributes;
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
			// Call getPlayers() to trigger the resolver that fetches all players
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
	const hasGame = !! game;
	const canManage = window.apermoScoreCards?.canManage ?? false;

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
						placeholder={ `Darts – ${ startingScore }` }
						help={ __(
							'Leave empty to use auto-generated title.',
							'apermo-score-cards'
						) }
					/>
					<SelectControl
						label={ __( 'Starting Score', 'apermo-score-cards' ) }
						value={ startingScore }
						options={ STARTING_SCORE_OPTIONS }
						onChange={ ( value ) =>
							setAttributes( {
								startingScore: parseInt( value, 10 ),
							} )
						}
						disabled={ hasGame }
						help={
							hasGame
								? __(
										'Cannot change after game started.',
										'apermo-score-cards'
								  )
								: ''
						}
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
					icon="marker"
					label={ __( 'Darts Score Card', 'apermo-score-cards' ) }
				>
					<Spinner />
				</Placeholder>
			) : ! hasPlayers ? (
				<Placeholder
					icon="marker"
					label={ __( 'Darts Score Card', 'apermo-score-cards' ) }
					instructions={ __(
						'Select at least 2 players from the sidebar to start.',
						'apermo-score-cards'
					) }
				>
					<p>
						{ __( 'Starting score:', 'apermo-score-cards' ) }{ ' ' }
						<strong>{ startingScore }</strong>
					</p>
				</Placeholder>
			) : (
				<div className="asc-darts">
					<div className="asc-darts__header">
						<h3 className="asc-darts__title">
							{ customTitle ||
								`${ __(
									'Darts',
									'apermo-score-cards'
								) } – ${ startingScore }` }
						</h3>
						{ game?.status === 'completed' && (
							<span className="asc-darts__status asc-darts__status--completed">
								{ __( 'Completed', 'apermo-score-cards' ) }
							</span>
						) }
					</div>

					{ game ? (
						<DartsScoreDisplay
							game={ game }
							players={ players }
							canEdit={ canManage }
						/>
					) : (
						<div className="asc-darts__pending">
							<Notice status="info" isDismissible={ false }>
								{ __(
									'Players selected. Save/publish the post, then enter scores on the frontend.',
									'apermo-score-cards'
								) }
							</Notice>
							<table className="asc-darts-display__table">
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
											<td className="asc-darts-display__player">
												{ player.avatarUrl && (
													<img
														src={ player.avatarUrl }
														alt=""
														className="asc-darts-display__avatar"
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
