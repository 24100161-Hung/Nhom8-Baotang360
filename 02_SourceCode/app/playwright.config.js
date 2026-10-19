// Cấu hình Playwright cho BaoTang360 (qa-tester).
// - Bộ chức năng (*.spec.js trừ matrix) chạy trên Chromium (có thao tác ghi: giỏ hàng, đơn, thanh toán…).
// - Bộ ma trận tương thích (matrix.spec.js) chạy trên chromium/firefox/webkit × 3 khổ (375, 768, 1280).
import { defineConfig, devices } from '@playwright/test';

const baseURL = process.env.E2E_BASE_URL || 'http://localhost:8090';
const matrix = /matrix\.spec\.js/;
const gl = { launchOptions: { args: ['--use-gl=swiftshader', '--enable-webgl', '--ignore-gpu-blocklist'] } };

export default defineConfig({
    testDir: './tests/e2e',
    outputDir: './tests/e2e/test-results',
    globalSetup: './tests/e2e/global-setup.js',
    fullyParallel: false,
    workers: 1,
    retries: 0,
    timeout: 90_000,
    expect: { timeout: 15_000 },
    reporter: [
        ['list'],
        ['html', { outputFolder: './tests/e2e/report', open: 'never' }],
        ['json', { outputFile: './tests/e2e/report/results.json' }],
    ],
    use: {
        baseURL,
        locale: 'vi-VN',
        timezoneId: 'Asia/Ho_Chi_Minh',
        trace: 'retain-on-failure',
        screenshot: 'only-on-failure',
        viewport: { width: 1280, height: 800 },
    },
    projects: [
        { name: 'chromium', use: { ...devices['Desktop Chrome'], viewport: { width: 1280, height: 800 }, ...gl } },
        { name: 'firefox', testMatch: matrix, use: { ...devices['Desktop Firefox'], viewport: { width: 1280, height: 800 } } },
        { name: 'webkit', testMatch: matrix, use: { ...devices['Desktop Safari'], viewport: { width: 1280, height: 800 } } },
    ],
});
