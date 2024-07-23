const path = require( 'path' );
const defaultConfig = require( '@wordpress/scripts/config/webpack.config' );

module.exports = {
	...defaultConfig,
	entry: {
		"troubleshooting": [
			path.resolve( process.cwd(), 'src/javascript', 'troubleshooting.js' ),
			path.resolve( process.cwd(), 'src/styles', 'troubleshooting.scss' )
		],
	}
}
