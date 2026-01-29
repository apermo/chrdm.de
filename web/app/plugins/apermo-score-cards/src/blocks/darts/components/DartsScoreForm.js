/**
 * Darts Score Form Component
 *
 * Form for entering final scores for each player.
 */

import { __ } from '@wordpress/i18n';
import {
	Button,
	TextControl,
	Spinner,
} from '@wordpress/components';
import { useState } from '@wordpress/element';
import { useDispatch } from '@wordpress/data';

import { STORE_NAME } from '../../../stores';
import { determineWinners } from '../scoring';

export default function DartsScoreForm( {
	playerIds,
	players,
	startingScore,
	postId,
	blockId,
} ) {
	const [ scores, setScores ] = useState( () => {
		const initial = {};
		playerIds.forEach( ( id ) => {
			initial[ id ] = { finalScore: '', finishedRound: '' };
		} );
		return initial;
	} );
	const [ isSaving, setIsSaving ] = useState( false );
	const [ errors, setErrors ] = useState( {} );

	const { saveGame } = useDispatch( STORE_NAME );

	const validateScore = ( value ) => {
		if ( value === '' ) {
			return __( 'Score is required', 'apermo-score-cards' );
		}
		const num = parseInt( value, 10 );
		if ( isNaN( num ) || num < 0 || num > startingScore ) {
			return sprintf(
				/* translators: %d: maximum score */
				__( 'Score must be between 0 and %d', 'apermo-score-cards' ),
				startingScore
			);
		}
		return null;
	};

	const handleScoreChange = ( playerId, field, value ) => {
		setScores( ( prev ) => ( {
			...prev,
			[ playerId ]: {
				...prev[ playerId ],
				[ field ]: value,
			},
		} ) );

		// Clear error when user types
		if ( errors[ playerId ] ) {
			setErrors( ( prev ) => {
				const newErrors = { ...prev };
				delete newErrors[ playerId ];
				return newErrors;
			} );
		}
	};

	const handleSubmit = async () => {
		// Validate all scores
		const newErrors = {};
		playerIds.forEach( ( id ) => {
			const error = validateScore( scores[ id ].finalScore );
			if ( error ) {
				newErrors[ id ] = error;
			}
		} );

		if ( Object.keys( newErrors ).length > 0 ) {
			setErrors( newErrors );
			return;
		}

		setIsSaving( true );

		// Convert scores to proper format
		const formattedScores = {};
		playerIds.forEach( ( id ) => {
			formattedScores[ id ] = {
				finalScore: parseInt( scores[ id ].finalScore, 10 ),
				finishedRound: scores[ id ].finishedRound
					? parseInt( scores[ id ].finishedRound, 10 )
					: null,
			};
		} );

		// Determine winners (players with 0 score, or lowest if no zeros)
		const winnerIds = determineWinners( formattedScores );
		const finalScores = {};
		Object.entries( formattedScores ).forEach( ( [ id, data ] ) => {
			finalScores[ id ] = data.finalScore;
		} );

		try {
			await saveGame( postId, blockId, {
				gameType: 'darts',
				playerIds,
				status: 'completed',
				rounds: [], // Darts doesn't track rounds in detail
				scores: formattedScores,
				finalScores,
				winnerIds,
				winnerId: winnerIds[ 0 ] || null,
			} );
		} catch ( error ) {
			console.error( 'Failed to save game:', error );
		}

		setIsSaving( false );
	};

	return (
		<div className="asc-darts-form">
			<table className="asc-darts-form__table">
				<thead>
					<tr>
						<th>{ __( 'Player', 'apermo-score-cards' ) }</th>
						<th>{ __( 'Final Score', 'apermo-score-cards' ) }</th>
						<th>{ __( 'Finished Round', 'apermo-score-cards' ) }</th>
					</tr>
				</thead>
				<tbody>
					{ players.map( ( player ) => (
						<tr key={ player.id } className="asc-darts-form__row">
							<td className="asc-darts-form__player">
								{ player.avatarUrl && (
									<img
										src={ player.avatarUrl }
										alt=""
										className="asc-darts-form__avatar"
									/>
								) }
								<span>{ player.name }</span>
							</td>
							<td className="asc-darts-form__score">
								<TextControl
									type="number"
									min={ 0 }
									max={ startingScore }
									value={ scores[ player.id ]?.finalScore || '' }
									onChange={ ( value ) =>
										handleScoreChange( player.id, 'finalScore', value )
									}
									placeholder={ `0-${ startingScore }` }
									className={
										errors[ player.id ] ? 'has-error' : ''
									}
								/>
								{ errors[ player.id ] && (
									<span className="asc-darts-form__error">
										{ errors[ player.id ] }
									</span>
								) }
							</td>
							<td className="asc-darts-form__round">
								<TextControl
									type="number"
									min={ 1 }
									value={ scores[ player.id ]?.finishedRound || '' }
									onChange={ ( value ) =>
										handleScoreChange( player.id, 'finishedRound', value )
									}
									placeholder={ __( 'Optional', 'apermo-score-cards' ) }
								/>
							</td>
						</tr>
					) ) }
				</tbody>
			</table>

			<div className="asc-darts-form__actions">
				<Button
					variant="primary"
					onClick={ handleSubmit }
					disabled={ isSaving }
				>
					{ isSaving ? (
						<>
							<Spinner />
							{ __( 'Saving…', 'apermo-score-cards' ) }
						</>
					) : (
						__( 'Save Results', 'apermo-score-cards' )
					) }
				</Button>
			</div>

			<p className="asc-darts-form__help">
				{ __(
					'Enter 0 for players who finished. Winners are determined by who reached 0 first (lowest round number).',
					'apermo-score-cards'
				) }
			</p>
		</div>
	);
}