import { defineConfig } from 'vitest/config';

/*
 * Unit tests for the pure browser modules in resources/js. They run under
 * Node, so those modules must not touch window or document when imported.
 */
export default defineConfig({
    test: {
        environment: 'node',
        include: ['tests/js/**/*.test.js'],
    },
});
