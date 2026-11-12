/** @type {import('tailwindcss').Config} */
export default {
    content: [
        './resources/views/**/*.blade.php',
        './resources/js/**/*.js',
        './app/**/*.php',
        './public/js/**/*.js',
    ],

    // The legacy design system (cvsu-theme / cvsu-product) is still bundled, so
    // preflight stays off and every utility is scoped under `body` to outrank its
    // single-class rules. Remove both once the legacy CSS is retired.
    corePlugins: { preflight: false },
    important: 'body',

    theme: {
        extend: {
            fontFamily: {
                sans: ['"Plus Jakarta Sans"', 'Poppins', 'ui-sans-serif', 'system-ui', '-apple-system', '"Segoe UI"', 'Roboto', 'sans-serif'],
            },
            colors: {
                cvsu: {
                    green: {
                        50: '#f0fdf4', 100: '#dcfce7', 300: '#6ee7a0', 400: '#34c759', 500: '#28a745',
                        600: '#1e7e34', 700: '#166534', 800: '#14532d', 900: '#0b3d1e',
                    },
                    gold: {
                        50: '#fffaeb', 100: '#fff6d6', 300: '#ffd24d', 400: '#f0c000', 500: '#e0a000',
                        600: '#b8860b', 700: '#8a6300',
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
            borderRadius: { card: '12px', 'card-sm': '8px' },
        },
    },
    plugins: [],
};
