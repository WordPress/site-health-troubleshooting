import { execFileSync } from 'child_process';
import { DEBUG_LOG_PATH } from './common';

const TEST_SITE_URL = 'http://localhost:8889/wp-login.php';
const READY_TIMEOUT = 10 * 60 * 1000;
const READY_INTERVAL = 1000;

async function waitForTestSite() {
	const deadline = Date.now() + READY_TIMEOUT;

	while ( Date.now() < deadline ) {
		try {
			const response = await fetch( TEST_SITE_URL, { redirect: 'manual' } );

			if ( response.ok || response.status === 301 || response.status === 302 ) {
				return;
			}
		} catch ( error ) {
			// `wp-env start` can return before Apache accepts connections; keep polling.
		}

		await new Promise( ( resolve ) => setTimeout( resolve, READY_INTERVAL ) );
	}

	throw new Error( `Timed out waiting for wp-env test site at ${ TEST_SITE_URL }.` );
}

/**
 * Ensures `wp-env` is up before Playwright's `webServer` phase so the
 * upstream-style reset can run reliably from a cold start, then wipes the
 * `tests` database and truncates the debug log for this run.
 */
async function globalSetup() {
	execFileSync( 'npx', [ 'wp-env', 'start' ], { stdio: 'inherit' } );

	execFileSync( 'npx', [ 'wp-env', 'clean', 'tests' ], { stdio: 'inherit' } );

	execFileSync(
		'npx',
		[ 'wp-env', 'run', 'tests-cli', 'wp', 'core', 'update-db' ],
		{ stdio: 'inherit' }
	);

	execFileSync(
		'npx',
		[
			'wp-env',
			'run',
			'tests-cli',
			'wp',
			'eval',
			`file_put_contents( '${ DEBUG_LOG_PATH }', '' );`,
		],
		{ stdio: 'inherit' }
	);

	await waitForTestSite();
}

export default globalSetup;
