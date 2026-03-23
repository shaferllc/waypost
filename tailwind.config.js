import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';

/** @type {import('tailwindcss').Config} */
export default {
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
    ],

    theme: {
        extend: {
            fontFamily: {
                sans: ['Figtree', ...defaultTheme.fontFamily.sans],
            },
            colors: {
                ink: {
                    DEFAULT: '#2A261C',
                },
                cream: {
                    50: '#FFFCF8',
                    100: '#FAF6ED',
                    200: '#EDE4D3',
                    300: '#D8CFC0',
                    400: '#C4B8A8',
                },
                sage: {
                    DEFAULT: '#6F9D8D',
                    light: '#8EB5A8',
                    dark: '#5a8678',
                    deeper: '#4a6f62',
                },
                gold: {
                    DEFAULT: '#C9AB5B',
                    pale: '#E8D9A2',
                    deep: '#956F34',
                },
                umber: {
                    DEFAULT: '#51341A',
                    muted: '#665228',
                },
            },
            boxShadow: {
                sage: '0 10px 40px -10px rgb(111 157 141 / 0.35)',
            },
        },
    },

    plugins: [forms],
};
