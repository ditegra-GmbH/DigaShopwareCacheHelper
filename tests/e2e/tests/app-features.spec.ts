import { test, expect } from '@playwright/test';

const baseUrl =  process.env.TESTSHOPURL as string;
const adminUser =  process.env.ADMIN as string;
const adminPass =  process.env.ADMINPSWD as string;

test.describe('admin tests', () => {

    test.beforeEach(async ({ page }) => {
        await page.goto(baseUrl + '/admin#/login');
        await page.getByPlaceholder('Gib Deinen Benutzernamen ein ...').click();
        await page.getByPlaceholder('Gib Deinen Benutzernamen ein ...').fill(adminUser);
        await page.getByPlaceholder('Gib Dein Passwort ein ...').fill(adminPass);
        await page.getByPlaceholder('Gib Dein Passwort ein ...').press('Enter');
    });

    test('install and activate plugin', async ({ page }) => {

        await page.getByText('Extensions', { exact: true }).click();
        await page.getByRole('link', { name: 'My extensions' }).first().click();
        
        const swExtensionCard = page.locator('div.sw-extension-card-base', { has: page.getByText('Your Plugin Name Here')});   
      
        await expect(swExtensionCard).toBeVisible();
        
        await swExtensionCard.getByText('Install', { exact: true }).click();
      
        await swExtensionCard.getByText('(inactive)').waitFor();
      
        await swExtensionCard.getByRole('checkbox').check();
      
        await expect(swExtensionCard.getByRole('checkbox')).toBeChecked();
    });

});