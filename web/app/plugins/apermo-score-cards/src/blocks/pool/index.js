/**
 * Pool Billiard block
 */

import { registerBlockType } from '@wordpress/blocks';

import metadata from './block.json';
import Edit from './edit';

import './style.scss';
import './editor.scss';

registerBlockType( metadata.name, {
	edit: Edit,
	save: () => null, // Dynamic block, rendered via PHP
} );
