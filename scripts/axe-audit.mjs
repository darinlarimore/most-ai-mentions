#!/usr/bin/env node

import puppeteer from 'puppeteer';
import AxeBuilder from '@axe-core/puppeteer';

const url = process.argv[2];

if (!url) {
    console.error(JSON.stringify({ error: 'URL argument required' }));
    process.exit(1);
}

const TIMEOUT_MS = 30_000;
const PAGE_SETTLE_MS = 3_000;

const timeout = setTimeout(() => {
    console.error(JSON.stringify({ error: 'Timeout exceeded' }));
    process.exit(1);
}, TIMEOUT_MS);

let browser;

try {
    browser = await puppeteer.launch({
        headless: true,
        args: [
            '--no-sandbox',
            '--disable-setuid-sandbox',
            '--disable-dev-shm-usage',
            '--disable-gpu',
            '--disable-software-rasterizer',
        ],
    });

    const page = await browser.newPage();
    await page.setViewport({ width: 1280, height: 720 });

    await page.goto(url, { waitUntil: 'load', timeout: 20_000 });

    // Wait for the page to settle — JS frameworks need time to hydrate
    await new Promise((resolve) => setTimeout(resolve, PAGE_SETTLE_MS));

    const results = await new AxeBuilder(page).analyze();

    const violations_summary = results.violations.map((v) => ({
        id: v.id,
        impact: v.impact,
        description: v.description,
        nodes_count: v.nodes.length,
    }));

    console.log(
        JSON.stringify({
            violations_count: results.violations.reduce((sum, v) => sum + v.nodes.length, 0),
            passes_count: results.passes.length,
            violations_summary,
        }),
    );
} catch (err) {
    console.error(JSON.stringify({ error: err.message }));
    process.exit(1);
} finally {
    clearTimeout(timeout);
    if (browser) {
        await browser.close();
    }
}
