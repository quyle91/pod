//  node build.js --watch
const fs = require('fs');
const path = require('path');
const esbuild = require('esbuild');
const sass = require('sass');

const isWatch = process.argv.includes('--watch');

const scssEntry = path.join(__dirname, 'assets/scss/pod-customizer.scss');
const cssOut = path.join(__dirname, 'assets/css/pod-customizer.css');

const jsEntry = path.join(__dirname, 'assets/js/src/main.js');
const jsOut = path.join(__dirname, 'assets/js/pod-customizer.js');

function buildCSS() {
    if (!fs.existsSync(scssEntry)) return;
    try {
        const start = Date.now();
        const result = sass.compile(scssEntry, {
            style: 'expanded',
            sourceMap: false,
        });
        fs.writeFileSync(cssOut, result.css, 'utf8');
        console.log(`[SCSS] Built ${cssOut} in ${Date.now() - start}ms`);
    } catch (err) {
        console.error('[SCSS Error]', err.message);
    }
}

async function buildJS() {
    if (!fs.existsSync(jsEntry)) return;
    try {
        const start = Date.now();
        await esbuild.build({
            entryPoints: [jsEntry],
            outfile: jsOut,
            bundle: true,
            format: 'iife',
            target: ['es2020'],
            minify: false,
        });
        console.log(`[JS] Bundled ${jsOut} in ${Date.now() - start}ms`);
    } catch (err) {
        console.error('[JS Error]', err.message);
    }
}

async function run() {
    buildCSS();
    await buildJS();

    if (isWatch) {
        console.log('[WATCH] Watching for changes in assets/scss and assets/js/src...');
        const scssDir = path.join(__dirname, 'assets/scss');
        const jsDir = path.join(__dirname, 'assets/js/src');

        if (fs.existsSync(scssDir)) {
            fs.watch(scssDir, { recursive: true }, (eventType, filename) => {
                if (filename && filename.endsWith('.scss')) {
                    console.log(`[SCSS] Change detected in ${filename}, rebuilding...`);
                    buildCSS();
                }
            });
        }

        if (fs.existsSync(jsDir)) {
            fs.watch(jsDir, { recursive: true }, (eventType, filename) => {
                if (filename && filename.endsWith('.js')) {
                    console.log(`[JS] Change detected in ${filename}, rebundling...`);
                    buildJS();
                }
            });
        }
    } else {
        process.exit(0);
    }
}

run();
