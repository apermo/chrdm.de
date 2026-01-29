/**
 * Player Selector Component
 *
 * Allows selecting players for a game from the list of WordPress users.
 */

import { useSelect } from '@wordpress/data';
import {
	CheckboxControl,
	Spinner,
	SearchControl,
} from '@wordpress/components';
import { useState, useMemo } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { STORE_NAME } from '../stores';

export default function PlayerSelector( {
	selectedPlayerIds = [],
	onChange,
	minPlayers = 2,
	maxPlayers = 10,
} ) {
	const [ searchTerm, setSearchTerm ] = useState( '' );

	const { players, isLoading } = useSelect( ( select ) => {
		const storeSelectors = select( STORE_NAME );
		return {
			players: storeSelectors.getPlayers(),
			isLoading: ! storeSelectors.arePlayersLoaded(),
		};
	}, [] );

	const filteredPlayers = useMemo( () => {
		if ( ! searchTerm ) {
			return players;
		}
		const term = searchTerm.toLowerCase();
		return players.filter( ( player ) =>
			player.name.toLowerCase().includes( term )
		);
	}, [ players, searchTerm ] );

	const handleTogglePlayer = ( playerId, isSelected ) => {
		let newSelection;

		if ( isSelected ) {
			if ( selectedPlayerIds.length >= maxPlayers ) {
				return; // Max players reached
			}
			newSelection = [ ...selectedPlayerIds, playerId ];
		} else {
			newSelection = selectedPlayerIds.filter( ( id ) => id !== playerId );
		}

		onChange( newSelection );
	};

	if ( isLoading ) {
		return (
			<div className="asc-player-selector asc-player-selector--loading">
				<Spinner />
				<span>{ __( 'Loading players…', 'apermo-score-cards' ) }</span>
			</div>
		);
	}

	if ( players.length === 0 ) {
		return (
			<div className="asc-player-selector asc-player-selector--empty">
				<p>
					{ __(
						'No players found. Add users in the WordPress admin.',
						'apermo-score-cards'
					) }
				</p>
			</div>
		);
	}

	const selectionCount = selectedPlayerIds.length;
	const canSelectMore = selectionCount < maxPlayers;

	return (
		<div className="asc-player-selector">
			<div className="asc-player-selector__header">
				<span className="asc-player-selector__count">
					{ sprintf(
						/* translators: 1: selected count, 2: min players, 3: max players */
						__( '%1$d selected (min: %2$d, max: %3$d)', 'apermo-score-cards' ),
						selectionCount,
						minPlayers,
						maxPlayers
					) }
				</span>
			</div>

			{ players.length > 5 && (
				<SearchControl
					value={ searchTerm }
					onChange={ setSearchTerm }
					placeholder={ __( 'Search players…', 'apermo-score-cards' ) }
				/>
			) }

			<div className="asc-player-selector__list">
				{ filteredPlayers.map( ( player ) => {
					const isSelected = selectedPlayerIds.includes( player.id );
					const isDisabled = ! isSelected && ! canSelectMore;

					return (
						<div
							key={ player.id }
							className={ `asc-player-selector__item ${
								isSelected ? 'is-selected' : ''
							} ${ isDisabled ? 'is-disabled' : '' }` }
						>
							<CheckboxControl
								checked={ isSelected }
								onChange={ ( checked ) =>
									handleTogglePlayer( player.id, checked )
								}
								disabled={ isDisabled }
								label={
									<span className="asc-player-selector__player">
										{ player.avatarUrl && (
											<img
												src={ player.avatarUrl }
												alt=""
												className="asc-player-selector__avatar"
											/>
										) }
										<span className="asc-player-selector__name">
											{ player.name }
										</span>
									</span>
								}
							/>
						</div>
					);
				} ) }
			</div>

			{ selectionCount < minPlayers && (
				<p className="asc-player-selector__warning">
					{ sprintf(
						/* translators: %d: minimum number of players required */
						__( 'Please select at least %d players.', 'apermo-score-cards' ),
						minPlayers
					) }
				</p>
			) }
		</div>
	);
}