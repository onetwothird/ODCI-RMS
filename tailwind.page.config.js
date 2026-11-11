/*
 * NOTE: no imports from 'tailwindcss/*' here - the CLI (via npx) runs the
 * config through jiti without a local node_modules install, so bare
 * imports like `tailwindcss/defaultTheme` cannot be resolved.
 *
 * Standalone Tailwind build for ODCI module pages.
 *
 * `preflight` is disabled so this stylesheet never resets the legacy
 * component CSS (sidebar / navbar / cvsu-theme) that the layouts load.
 * The colours are the CVSU design tokens from public/css/cvsu-theme.css.
 *
 * Build:
 *   npx tailwindcss -c tailwind.page.config.js \
 *     -i resources/css/tailwind-page.css -o public/css/tailwind.css --minify
 */
export default {
    content: [
        './resources/views/**/*.blade.php',
        './resources/js/**/*.js',
    ],
    corePlugins: {
        preflight: false,
    },
    // Scope every utility under `body` so it outranks the single-class rules in
    // cvsu-theme.css / cvsu-product.css (e.g. `.grid { grid-template-columns:
    // auto-fill }`) that load after this sheet. Selector strategy, not
    // `!important`, so inline styles set from JS still win.
    important: 'body',
    theme: {
        extend: {
            fontFamily: {
                sans: ['"Plus Jakarta Sans"', 'ui-sans-serif', 'system-ui', '-apple-system', '"Segoe UI"', 'Roboto', 'sans-serif'],
            },
            colors: {
                cvsu: {
                    green: {
                        50: '#f0fdf4',
                        100: '#dcfce7',
                        300: '#6ee7a0',
                        400: '#34c759',
                        500: '#28a745',
                        600: '#1e7e34',
                        700: '#166534',
                        800: '#14532d',
                        900: '#0b3d1e',
                    },
                    gold: {
                        50: '#fffaeb',
                        100: '#fff6d6',
                        300: '#ffd24d',
                        400: '#f0c000',
                        500: '#e0a000',
                        600: '#b8860b',
                        700: '#8a6300',
                    },
                },
                ink: '#16241c',
                muted: '#6b7280',
                line: '#e6ebe7',
                danger: '#dc2626',
            },
            boxShadow: {
                card: '0 2px 8px rgba(11, 61, 30, .07), 0 1px 2px rgba(11, 61, 30, .04)',
                'card-md': '0 6px 20px rgba(11, 61, 30, .10), 0 2px 6px rgba(11, 61, 30, .05)',
            },
            borderRadius: {
                card: '12px',
                'card-sm': '8px',
            },
        },
    },
    plugins: [],
};