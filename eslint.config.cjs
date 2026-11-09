const wpPlugin = require( '@wordpress/eslint-plugin' );

module.exports = [
	{
		ignores: [ '.docker/**', '.github/**', 'build/**', 'node_modules/**', 'vendor/**' ],
	},
	...wpPlugin.configs.esnext,
	{
		languageOptions: {
			globals: {
				jQuery: 'readonly',
				document: 'readonly',
				window: 'readonly',
				wp: 'readonly',
			},
		},
	},
];
