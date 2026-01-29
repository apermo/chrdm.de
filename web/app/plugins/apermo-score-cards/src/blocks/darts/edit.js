/**
 * Darts Score Card - Editor Component
 */

import { __ } from '@wordpress/i18n';
import { useBlockProps, InspectorControls } from '@wordpress/block-editor';
import {
	PanelBody,
	SelectControl,
	Placeholder,
	Button,
	Spinner,
} from '@wordpress/components';
import { useSelect, useDispatch } from '@wordpress/data';
import { useEffect } from '@wordpress/element';
import { v4 as uuidv4 } from 'uuid';

import { STORE_NAME } from '../../stores';
import { PlayerSelector } from '../../components';
import DartsScoreForm from './components/DartsScoreForm';
import DartsScoreDisplay from './components/DartsScoreDisplay';

const STARTING_SCORE_OPTIONS = [
	{ label: '301', value: 301 },
	{ label: '501', value: 501 },
	{ label: '701', value: 701 },
	{ label: '901', value: 901 },
];

export default function Edit( { attributes, setAttributes, context } ) {
	const { blockId, playerIds, startingScore } = attributes;
	const postId = context.postId;
	const blockProps = useBlockProps();

	// Generate block ID if not set
	useEffect( () => {
		if ( ! blockId ) {
			setAttributes( { blockId: uuidv4() } );
		}
	}, [ blockId, setAttributes ] );

	// Fetch players and game data
	const { players, isLoading, game, canManage } = useSelect(
		( select ) => {
			const store = select( STORE_NAME );
			return {
				players: store.getPlayersByIds( playerIds ),
				isLoading: ! store.arePlayersLoaded(),
				game: blockId ? store.getGame( postId, blockId ) : null,
				canManage: store.canManageScorecard( postId ),
			};
		},
		[ playerIds, postId, blockId ]
	);

	const { fetchGame, fetchPermissions } = useDispatch( STORE_NAME );

	// Fetch game data and permissions on mount
	useEffect( () => {
		if ( postId && blockId ) {
			fetchGame( postId, blockId );
			fetchPermissions( postId );
		}
	}, [ postId, blockId, fetchGame, fetchPermissions ] );

	const hasPlayers = playerIds.length >= 2;
	const hasGame = !! game;

	return (
		<div { ...blockProps }>
			<InspectorControls>
				<PanelBody title={ __( 'Game Settings', 'apermo-score-cards' ) }>
					<SelectControl
						label={ __( 'Starting Score', 'apermo-score-cards' ) }
						value={ startingScore }
						options={ STARTING_SCORE_OPTIONS }
						onChange={ ( value ) =>
							setAttributes( { startingScore: parseInt( value, 10 ) } )
						}
						disabled={ hasGame }
						help={
							hasGame
								? __( 'Cannot change after game started.', 'apermo-score-cards' )
								: ''
						}
					/>
				</PanelBody>

				<PanelBody
					title={ __( 'Players', 'apermo-score-cards' ) }
					initialOpen={ ! hasPlayers }
				>
					<PlayerSelector
						selectedPlayerIds={ playerIds }
						onChange={ ( newPlayerIds ) =>
							setAttributes( { playerIds: newPlayerIds } )
						}
						minPlayers={ 2 }
						maxPlayers={ 8 }
					/>
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
							{ __( 'Darts', 'apermo-score-cards' ) } – { startingScore }
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
							startingScore={ startingScore }
							postId={ postId }
							blockId={ blockId }
							isEditor={ true }
						/>
					) : (
						<DartsScoreForm
							playerIds={ playerIds }
							players={ players }
							startingScore={ startingScore }
							postId={ postId }
							blockId={ blockId }
						/>
					) }
				</div>
			) }
		</div>
	);
}