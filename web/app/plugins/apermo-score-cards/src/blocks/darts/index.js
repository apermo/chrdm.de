/**
 * Darts Score Card Block
 */

import { registerBlockType } from '@wordpress/blocks';
import { useBlockProps } from '@wordpress/block-editor';

import metadata from './block.json';
import Edit from './edit';

import './editor.scss';
import './style.scss';

registerBlockType( metadata.name, {
	edit: Edit,
	save: () => {
		// Dynamic block - rendered via PHP
		return null;
	},
} );
