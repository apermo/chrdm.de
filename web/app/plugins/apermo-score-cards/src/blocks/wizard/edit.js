/**
 * Wizard Score Card - Editor Component
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
import WizardScoreDisplay from './components/WizardScoreDisplay';

/**
 * Calculate total rounds based on player count.
 * Standard Wizard: 60 cards / players = rounds.
 *
 * @param {number} playerCount Number of players.
 * @return {number} Total rounds.
 */
function getTotalRounds( playerCount ) {
	if ( playerCount < 3 || playerCount > 6 ) {
		return 0;
	}
	return Math.floor( 60 / playerCount );
}

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

	const hasPlayers = playerIds.length >= 3 && playerIds.length <= 6;
	const hasGame = !! game;
	const totalRounds = getTotalRounds( playerIds.length );

	return (
		<div { ...blockProps }>
			<InspectorControls>
				<PanelBody title={ __( 'Game Settings', 'apermo-score-cards' ) }>
					<TextControl
						label={ __( 'Custom Title', 'apermo-score-cards' ) }
						value={ customTitle }
						onChange={ ( value ) => setAttributes( { customTitle: value } ) }
						placeholder={ __( 'Wizard', 'apermo-score-cards' ) }
						help={ __( 'Leave empty to use auto-generated title.', 'apermo-score-cards' ) }
					/>
					{ hasPlayers && (
						<p className="components-base-control__help">
							{ __( 'Total rounds:', 'apermo-score-cards' ) } <strong>{ totalRounds }</strong>
						</p>
					) }
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
						minPlayers={ 3 }
						maxPlayers={ 6 }
						disabled={ hasGame }
					/>
					{ hasGame && (
						<p className="components-base-control__help">
							{ __( 'Cannot change players after game started.', 'apermo-score-cards' ) }
						</p>
					) }
				</PanelBody>
			</InspectorControls>

			{ isLoading ? (
				<Placeholder
					icon="superhero"
					label={ __( 'Wizard Score Card', 'apermo-score-cards' ) }
				>
					<Spinner />
				</Placeholder>
			) : ! hasPlayers ? (
				<Placeholder
					icon="superhero"
					label={ __( 'Wizard Score Card', 'apermo-score-cards' ) }
					instructions={ __(
						'Select 3-6 players from the sidebar to start.',
						'apermo-score-cards'
					) }
				>
					{ playerIds.length > 0 && playerIds.length < 3 && (
						<Notice status="warning" isDismissible={ false }>
							{ __( 'Wizard requires at least 3 players.', 'apermo-score-cards' ) }
						</Notice>
					) }
					{ playerIds.length > 6 && (
						<Notice status="warning" isDismissible={ false }>
							{ __( 'Wizard supports maximum 6 players.', 'apermo-score-cards' ) }
						</Notice>
					) }
				</Placeholder>
			) : (
				<div className="asc-wizard">
					<div className="asc-wizard__header">
						<h3 className="asc-wizard__title">
							{ customTitle || __( 'Wizard', 'apermo-score-cards' ) }
						</h3>
						<span className="asc-wizard__rounds">
							{ totalRounds } { __( 'rounds', 'apermo-score-cards' ) }
						</span>
						{ game?.status === 'completed' && (
							<span className="asc-wizard__status asc-wizard__status--completed">
								{ __( 'Completed', 'apermo-score-cards' ) }
							</span>
						) }
					</div>

					{ game ? (
						<WizardScoreDisplay
							game={ game }
							players={ players }
							totalRounds={ totalRounds }
						/>
					) : (
						<div className="asc-wizard__pending">
							<Notice status="info" isDismissible={ false }>
								{ __(
									'Players selected. Save/publish the post, then enter scores on the frontend.',
									'apermo-score-cards'
								) }
							</Notice>
							<table className="asc-wizard-display__table">
								<thead>
									<tr>
										<th>#</th>
										<th>{ __( 'Player', 'apermo-score-cards' ) }</th>
									</tr>
								</thead>
								<tbody>
									{ players.map( ( player, index ) => (
										<tr key={ player.id }>
											<td>{ index + 1 }</td>
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
