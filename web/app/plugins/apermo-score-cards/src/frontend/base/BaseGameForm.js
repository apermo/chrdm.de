/**
 * Base Game Form Class
 *
 * Abstract base class for all game score entry forms.
 * Provides shared functionality for rendering, validation, and API calls.
 */

import { saveGame, addRound, updateRound, resetGame } from './api';
import { escapeHtml, __, formatPlayerHtml } from './utils';

export default class BaseGameForm {
	/**
	 * Create a new game form.
	 *
	 * @param {HTMLElement} container        DOM element to render the form into.
	 * @param {Object}      options          Form configuration options.
	 * @param {number}      options.postId   Post ID containing the block.
	 * @param {string}      options.blockId  Block ID.
	 * @param {Array}       options.players  Array of player objects.
	 * @param {Function}    options.onSave   Callback after successful save.
	 * @param {Function}    options.onCancel Callback when form is cancelled.
	 */
	constructor( container, options = {} ) {
		this.container = container;
		this.postId = options.postId;
		this.blockId = options.blockId;
		this.players = options.players || [];
		this.onSave = options.onSave || ( () => {} );
		this.onCancel = options.onCancel || ( () => {} );

		this.isSubmitting = false;
	}

	// =========================================================================
	// Abstract methods - subclasses must implement
	// =========================================================================

	/**
	 * Get the game type identifier.
	 *
	 * @abstract
	 * @return {string} Game type (e.g., 'wizard', 'darts', 'pool', 'phase10').
	 */
	getGameType() {
		throw new Error( 'getGameType() must be implemented by subclass' );
	}

	/**
	 * Get the CSS class prefix for this form.
	 *
	 * @abstract
	 * @return {string} CSS prefix (e.g., 'asc-wizard-form', 'asc-darts-form').
	 */
	getCssPrefix() {
		throw new Error( 'getCssPrefix() must be implemented by subclass' );
	}

	/**
	 * Render the form content.
	 * This is the main content between title and buttons.
	 *
	 * @abstract
	 * @return {string} HTML string for form content.
	 */
	renderFormContent() {
		throw new Error(
			'renderFormContent() must be implemented by subclass'
		);
	}

	/**
	 * Validate the form data.
	 * Should display inline errors and return false if invalid.
	 *
	 * @abstract
	 * @return {boolean} True if valid, false otherwise.
	 */
	validateForm() {
		throw new Error( 'validateForm() must be implemented by subclass' );
	}

	/**
	 * Get the form data for submission.
	 *
	 * @abstract
	 * @return {Object} Data to send to the API.
	 */
	getFormData() {
		throw new Error( 'getFormData() must be implemented by subclass' );
	}

	// =========================================================================
	// Template methods - subclasses can override
	// =========================================================================

	/**
	 * Get the form title.
	 *
	 * @return {string} Form title text.
	 */
	getFormTitle() {
		return __( 'Enter Scores' );
	}

	/**
	 * Get the submit button text.
	 *
	 * @return {string} Submit button text.
	 */
	getSubmitText() {
		return __( 'Save' );
	}

	/**
	 * Get the cancel button text.
	 *
	 * @return {string} Cancel button text.
	 */
	getCancelText() {
		return __( 'Cancel' );
	}

	/**
	 * Whether to show the cancel button.
	 *
	 * @return {boolean} True to show cancel button.
	 */
	showCancelButton() {
		return typeof this.onCancel === 'function';
	}

	/**
	 * Called after rendering to bind events.
	 * Override to add custom event bindings.
	 */
	bindCustomEvents() {
		// Override in subclass if needed
	}

	/**
	 * Called after successful form submission.
	 * Default behavior is to call onSave callback.
	 *
	 * @param {Object} response API response data.
	 */
	onSubmitSuccess( response ) {
		this.onSave( response );
	}

	/**
	 * Submit the form data to the API.
	 * Override for custom submission logic.
	 *
	 * @return {Promise<Object>} API response.
	 */
	async submitForm() {
		const data = this.getFormData();
		return saveGame( this.postId, this.blockId, data );
	}

	// =========================================================================
	// Utility methods
	// =========================================================================

	/**
	 * Escape HTML for safe rendering.
	 *
	 * @param {string} text Text to escape.
	 * @return {string}     Escaped text.
	 */
	escapeHtml( text ) {
		return escapeHtml( text );
	}

	/**
	 * Translate text.
	 *
	 * @param {string} text Text to translate.
	 * @return {string}     Translated text.
	 */
	__( text ) {
		return __( text );
	}

	/**
	 * Render a player info element (avatar + name).
	 *
	 * @param {Object} player Player object.
	 * @return {string}       HTML string.
	 */
	renderPlayerInfo( player ) {
		return formatPlayerHtml( player, this.getCssPrefix() );
	}

	// =========================================================================
	// State management
	// =========================================================================

	/**
	 * Set the submitting state and update button.
	 *
	 * @param {boolean} submitting Whether form is submitting.
	 * @param {string}  buttonText Optional button text during submit.
	 */
	setSubmitting( submitting, buttonText = null ) {
		this.isSubmitting = submitting;
		const submitBtn = this.container.querySelector(
			`.${ this.getCssPrefix() }__submit`
		);

		if ( submitBtn ) {
			submitBtn.disabled = submitting;
			if ( buttonText ) {
				submitBtn.textContent = buttonText;
			} else if ( ! submitting ) {
				submitBtn.textContent = this.getSubmitText();
			}
		}
	}

	/**
	 * Show a message to the user.
	 *
	 * @param {string} message Message text.
	 * @param {string} type    Message type ('error', 'success').
	 */
	showMessage( message, type = 'error' ) {
		const messageEl = this.container.querySelector(
			`.${ this.getCssPrefix() }__message`
		);
		if ( messageEl ) {
			messageEl.textContent = message;
			messageEl.className = `${ this.getCssPrefix() }__message ${ this.getCssPrefix() }__message--${ type }`;
			messageEl.hidden = false;
		}
	}

	/**
	 * Hide the message element.
	 */
	hideMessage() {
		const messageEl = this.container.querySelector(
			`.${ this.getCssPrefix() }__message`
		);
		if ( messageEl ) {
			messageEl.hidden = true;
		}
	}

	// =========================================================================
	// Rendering
	// =========================================================================

	/**
	 * Render the complete form.
	 * Called automatically in constructor.
	 */
	render() {
		const prefix = this.getCssPrefix();
		const title = this.getFormTitle();

		this.container.innerHTML = `
			<form class="${ prefix }">
				${
					title
						? `<h4 class="${ prefix }__title">${ this.escapeHtml(
								title
						  ) }</h4>`
						: ''
				}
				${ this.renderFormContent() }
				<div class="${ prefix }__actions">
					${ this.renderActionButtons() }
				</div>
				<div class="${ prefix }__message" hidden></div>
			</form>
		`;

		this.bindEvents();
	}

	/**
	 * Render the action buttons.
	 *
	 * @return {string} HTML string for buttons.
	 */
	renderActionButtons() {
		const prefix = this.getCssPrefix();
		let html = `<button type="submit" class="${ prefix }__submit">${ this.escapeHtml(
			this.getSubmitText()
		) }</button>`;

		if ( this.showCancelButton() ) {
			html += `<button type="button" class="${ prefix }__cancel">${ this.escapeHtml(
				this.getCancelText()
			) }</button>`;
		}

		return html;
	}

	// =========================================================================
	// Event handling
	// =========================================================================

	/**
	 * Bind form events.
	 */
	bindEvents() {
		const form = this.container.querySelector( 'form' );
		const cancelBtn = this.container.querySelector(
			`.${ this.getCssPrefix() }__cancel`
		);

		if ( form ) {
			form.addEventListener( 'submit', ( e ) => this.handleSubmit( e ) );
		}

		if ( cancelBtn ) {
			cancelBtn.addEventListener( 'click', () => this.handleCancel() );
		}

		this.bindCustomEvents();
	}

	/**
	 * Handle form submission.
	 *
	 * @param {Event} e Submit event.
	 */
	async handleSubmit( e ) {
		e.preventDefault();

		if ( this.isSubmitting ) {
			return;
		}

		this.hideMessage();

		// Validate
		if ( ! this.validateForm() ) {
			return;
		}

		// Submit
		this.setSubmitting( true, this.__( 'Saving…' ) );

		try {
			const response = await this.submitForm();
			this.onSubmitSuccess( response );
		} catch ( error ) {
			console.error( `Failed to save ${ this.getGameType() }:`, error );
			this.showMessage(
				error.message || this.__( 'Failed to save. Please try again.' )
			);
			this.setSubmitting( false );
		}
	}

	/**
	 * Handle cancel button click.
	 */
	handleCancel() {
		this.onCancel();
	}

	// =========================================================================
	// API Helpers
	// =========================================================================

	/**
	 * Save game data via API.
	 *
	 * @param {Object} gameData Game data to save.
	 * @return {Promise<Object>} API response.
	 */
	async saveGame( gameData ) {
		return saveGame( this.postId, this.blockId, gameData );
	}

	/**
	 * Add a round via API.
	 *
	 * @param {Object} roundData Round data to add.
	 * @return {Promise<Object>} API response.
	 */
	async addRound( roundData ) {
		return addRound( this.postId, this.blockId, roundData );
	}

	/**
	 * Update a round via API.
	 *
	 * @param {number} index     Round index.
	 * @param {Object} roundData Updated round data.
	 * @return {Promise<Object>} API response.
	 */
	async updateRound( index, roundData ) {
		return updateRound( this.postId, this.blockId, index, roundData );
	}

	/**
	 * Reset/start a new game via API.
	 *
	 * @return {Promise<Object>} API response.
	 */
	async resetGame() {
		const playerIds = this.players.map( ( p ) => p.id );
		return resetGame(
			this.postId,
			this.blockId,
			this.getGameType(),
			playerIds
		);
	}
}
