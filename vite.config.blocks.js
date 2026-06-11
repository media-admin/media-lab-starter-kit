import { defineConfig } from 'vite';
import path from 'path';
import { fileURLToPath } from 'url';
import autoprefixer from 'autoprefixer';

const __filename = fileURLToPath(import.meta.url);
const __dirname  = path.dirname(__filename);
const pluginDir  = path.resolve(__dirname, 'cms/wp-content/plugins/media-lab-agency-core');

/**
 * Vite-Konfiguration: Gutenberg Blocks (Plugin-Assets)
 *
 * Separater Build für media-lab-agency-core/assets/dist/.
 * Aufruf:  vite build --config vite.config.blocks.js
 * Oder via: npm run build  (ruft beide Configs nacheinander auf)
 *
 * Format-Strategie (Vite 8 / rolldown):
 *   - format: 'iife' + mehrere Inputs → nicht erlaubt (codeSplitting-Konflikt)
 *   - Lösung: ES-Module-Format (rolldown-Standard) + type="module" via WP-Filter
 *   - ES-Module sind browser-seitig automatisch defer → löst wp.*-Timing mit
 *   - wp.domReady() in blocks.js als zusätzliche Absicherung beibehalten
 *
 * @since 1.6.0
 */
export default defineConfig({
  root: path.resolve(pluginDir, 'assets/src'),
  base: '/wp-content/plugins/media-lab-agency-core/assets/dist/',

  build: {
    outDir:      path.resolve(pluginDir, 'assets/dist'),
    emptyOutDir: true,

    rollupOptions: {
      input: {
        blocks:              path.resolve(pluginDir, 'assets/src/js/blocks.js'),
        'block-accordion':   path.resolve(pluginDir, 'assets/src/js/block-accordion.js'),
        'block-logo-slider': path.resolve(pluginDir, 'assets/src/js/block-logo-slider.js'),
        // 'blocks-scss': wird via sass CLI gebaut – Vite 8 Bug mit SCSS als Rollup-Input
      },

      external: [
        // @wordpress/* ESM-Imports nicht bundeln (für künftige Verwendung)
        /^@wordpress\/.*/,
      ],

      output: {
        // ES-Module: Standard in rolldown, unterstützt mehrere Inputs ohne Einschränkungen.
        // WordPress lädt diese via type="module" (s. script_loader_tag-Filter in inc/blocks.php).
        format: 'es',

        entryFileNames: 'js/[name].js',
        chunkFileNames: 'js/chunks/[name]-[hash].js',
        assetFileNames: (assetInfo) => {
          if (assetInfo.name?.endsWith('.css')) return 'css/[name].css';
          return 'assets/[name][extname]';
        },
      },
    },

    minify: 'terser',
    terserOptions: {
      compress: { drop_console: true, drop_debugger: true },
    },
  },

  css: {
    preprocessorOptions: {
      scss: { api: 'modern-compiler' },
    },
    postcss: {
      plugins: [
        autoprefixer({
          overrideBrowserslist: ['last 2 versions', '> 1%', 'not dead'],
        }),
      ],
    },
  },
});
