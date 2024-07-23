import { test, expect } from '@playwright/test';
import { signIn } from "../common";

test( 'Check that `Troubleshooting` is added as a tab to Site Health', async ( { page } ) => {
	await signIn( { page } );

	await page.goto( '/wp-admin/site-health.php' );

	await expect(
		page.locator( 'nav.health-check-tabs-wrapper' ).getByText( 'Troubleshooting' ),
		'The Troubleshooting tab was not found in the Site Health page.'
	).toBeVisible();
} );

test( 'Toggle troubleshooting mode from Site Health', async( { page } ) => {
	await signIn( { page } );

	await page.goto( '/wp-admin/site-health.php?tab=troubleshooting' );

	await page.locator( '[type=submit]' ).getByText( /Enable Troubleshooting Mode/ ).click();

	await page.waitForURL( '/wp-admin/' );

	await expect(
		page.locator( '#health-check-dashboard-widget' ),
		'Troubleshooting mode was not enabled.'
	).toBeVisible();

	await page.locator( '#health-check-dashboard-widget' ).locator( 'a.button' ).getByText( /Disable Troubleshooting Mode/ ).click();

	await page.waitForURL(
		'/wp-admin/',

	);

	await expect(
		page.locator( '#health-check-dashboard-widget' ),
		'Troubleshooting mode was not disabled.'
	).toHaveCount(0);
} );
