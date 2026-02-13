/**
 * ESLint configuration for Apermo Score Cards.
 *
 * Extends the WordPress ESLint configuration with custom rules.
 */
module.exports = {
	root: true,
	extends: [ 'plugin:@wordpress/eslint-plugin/recommended' ],
	env: {
		browser: true,
		es2022: true,
	},
	parserOptions: {
		ecmaVersion: 'latest',
		sourceType: 'module',
		ecmaFeatures: {
			jsx: true,
		},
	},
	globals: {
		wp: 'readonly',
		apermoScoreCards: 'readonly',
	},
	rules: {
		// Allow console.error for error logging
		'no-console': [ 'error', { allow: [ 'error', 'warn' ] } ],

		// Enforce consistent spacing
		'@wordpress/no-unsafe-wp-apis': 'warn',

		// Allow class methods that don't use 'this' (for override compatibility)
		'class-methods-use-this': 'off',
	},
	overrides: [
		{
			files: [ 'tests/**/*.js', '**/*.test.js', '**/*.spec.js' ],
			env: {
				jest: true,
				node: true,
			},
			rules: {
				'no-console': 'off',
			},
		},
	],
	ignorePatterns: [
		'build/',
		'node_modules/',
		'vendor/',
		'*.min.js',
	],
};
