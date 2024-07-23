import { test, expect } from '@playwright/test';
import { signIn } from "../common";

test( 'Verify the existence of troubleshooting links for plugins', async ( { page } ) => {
	await signIn( { page } );

	await page.goto( '/wp-admin/plugins.php' );

	await expect(
		page.locator( '.row-actions .troubleshoot' ),
		'No troubleshooting link was found as a plugin action.'
	).not.toHaveCount( 0 );
} );
