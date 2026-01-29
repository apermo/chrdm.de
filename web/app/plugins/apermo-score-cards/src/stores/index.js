/**
 * Data stores for Apermo Score Cards
 */

import { createReduxStore, register } from '@wordpress/data';
import apiFetch from '@wordpress/api-fetch';

const STORE_NAME = 'apermo-score-cards';
const API_NAMESPACE = '/apermo-score-cards/v1';

const DEFAULT_STATE = {
	players: [],
	playersLoaded: false,
	games: {},
	permissions: {},
};

const actions = {
	setPlayers( players ) {
		return {
			type: 'SET_PLAYERS',
			players,
		};
	},

	setGame( postId, blockId, game ) {
		return {
			type: 'SET_GAME',
			postId,
			blockId,
			game,
		};
	},

	fetchPlayers() {
		return async ( { dispatch } ) => {
			const players = await apiFetch( {
				path: `${ API_NAMESPACE }/players`,
			} );
			dispatch.setPlayers( players );
		};
	},

	fetchGame( postId, blockId ) {
		return async ( { dispatch } ) => {
			try {
				const game = await apiFetch( {
					path: `${ API_NAMESPACE }/posts/${ postId }/games/${ blockId }`,
				} );
				dispatch.setGame( postId, blockId, game );
			} catch ( error ) {
				// Game doesn't exist yet, that's fine
				if ( error.code !== 'not_found' ) {
					console.error( 'Failed to fetch game:', error );
				}
			}
		};
	},

	saveGame( postId, blockId, gameData ) {
		return async ( { dispatch } ) => {
			const game = await apiFetch( {
				path: `${ API_NAMESPACE }/posts/${ postId }/games/${ blockId }`,
				method: 'POST',
				data: gameData,
			} );
			dispatch.setGame( postId, blockId, game );
			return game;
		};
	},

	addRound( postId, blockId, roundData ) {
		return async ( { dispatch } ) => {
			const game = await apiFetch( {
				path: `${ API_NAMESPACE }/posts/${ postId }/games/${ blockId }/rounds`,
				method: 'POST',
				data: { roundData },
			} );
			dispatch.setGame( postId, blockId, game );
			return game;
		};
	},

	updateRound( postId, blockId, roundIndex, roundData ) {
		return async ( { dispatch } ) => {
			const game = await apiFetch( {
				path: `${ API_NAMESPACE }/posts/${ postId }/games/${ blockId }/rounds/${ roundIndex }`,
				method: 'PUT',
				data: { roundData },
			} );
			dispatch.setGame( postId, blockId, game );
			return game;
		};
	},

	completeGame( postId, blockId, finalScores, winnerId ) {
		return async ( { dispatch } ) => {
			const game = await apiFetch( {
				path: `${ API_NAMESPACE }/posts/${ postId }/games/${ blockId }/complete`,
				method: 'POST',
				data: { finalScores, winnerId },
			} );
			dispatch.setGame( postId, blockId, game );
			return game;
		};
	},

	setPermissions( postId, permissions ) {
		return {
			type: 'SET_PERMISSIONS',
			postId,
			permissions,
		};
	},

	fetchPermissions( postId ) {
		return async ( { dispatch } ) => {
			try {
				const permissions = await apiFetch( {
					path: `${ API_NAMESPACE }/posts/${ postId }/can-manage`,
				} );
				dispatch.setPermissions( postId, permissions );
				return permissions;
			} catch ( error ) {
				console.error( 'Failed to fetch permissions:', error );
				return null;
			}
		};
	},
};

const reducer = ( state = DEFAULT_STATE, action ) => {
	switch ( action.type ) {
		case 'SET_PLAYERS':
			return {
				...state,
				players: action.players,
				playersLoaded: true,
			};

		case 'SET_GAME':
			const gameKey = `${ action.postId }-${ action.blockId }`;
			return {
				...state,
				games: {
					...state.games,
					[ gameKey ]: action.game,
				},
			};

		case 'SET_PERMISSIONS':
			return {
				...state,
				permissions: {
					...state.permissions,
					[ action.postId ]: action.permissions,
				},
			};

		default:
			return state;
	}
};

const selectors = {
	getPlayers( state ) {
		return state.players;
	},

	arePlayersLoaded( state ) {
		return state.playersLoaded;
	},

	getGame( state, postId, blockId ) {
		const gameKey = `${ postId }-${ blockId }`;
		return state.games[ gameKey ] || null;
	},

	getPlayerById( state, playerId ) {
		return state.players.find( ( player ) => player.id === playerId ) || null;
	},

	getPlayersByIds( state, playerIds ) {
		return playerIds
			.map( ( id ) => state.players.find( ( player ) => player.id === id ) )
			.filter( Boolean );
	},

	getPermissions( state, postId ) {
		return state.permissions[ postId ] || null;
	},

	canManageScorecard( state, postId ) {
		const permissions = state.permissions[ postId ];
		return permissions?.canManage || false;
	},

	getRemainingEditTime( state, postId ) {
		const permissions = state.permissions[ postId ];
		return permissions?.remainingSeconds || 0;
	},
};

const resolvers = {
	*getPlayers() {
		yield actions.fetchPlayers();
	},
};

const store = createReduxStore( STORE_NAME, {
	reducer,
	actions,
	selectors,
	resolvers,
} );

register( store );

export default store;
export { STORE_NAME };
