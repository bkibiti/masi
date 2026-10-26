import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import { cpSync, mkdirSync } from 'node:fs';

/**
 * Pages call jQuery plugins from inline scripts while the page is still parsing,
 * before Vite modules run, so these libraries are served as classic scripts.
 */
const classicScripts = {
    'jquery.min.js': 'node_modules/jquery/dist/jquery.min.js',
    'dataTables.min.js': 'node_modules/datatables.net/js/dataTables.min.js',
    'dataTables.bootstrap5.min.js': 'node_modules/datatables.net-bs5/js/dataTables.bootstrap5.min.js',
    'select2.full.min.js': 'node_modules/select2/dist/js/select2.full.min.js',
};

function copyClassicScripts() {
    const copy = () => {
        mkdirSync('public/vendor', { recursive: true });
        for (const [target, source] of Object.entries(classicScripts)) {
            cpSync(source, `public/vendor/${target}`);
        }
    };

    return { name: 'copy-classic-scripts', buildStart: copy };
}

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.js'],
            refresh: true,
        }),
        copyClassicScripts(),
    ],
    server: {
        watch: {
            ignored: ['**/storage/framework/views/**'],
        },
    },
});
