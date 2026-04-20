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
                sans: ['Inter', ...defaultTheme.fontFamily.sans],
                display: ['Fraunces', ...defaultTheme.fontFamily.serif],
            },
            colors: {
                brand: {
                    cream: '#f4ece3',
                    ink: '#3c3025',
                    'ink-soft': '#6b5b4c',
                },
                category: {
                    fish: {
                        DEFAULT: '#1c397d',
                        soft: '#b5daea',
                    },
                    meat: {
                        DEFAULT: '#ae2033',
                        soft: '#e5bcc0',
                    },
                    produce: {
                        DEFAULT: '#21341d',
                        soft: '#79954d',
                    },
                    dairy: {
                        DEFAULT: '#3c3025',
                        soft: '#ffffff',
                    },
                    bakery: {
                        DEFAULT: '#3c3025',
                        soft: '#ffffff',
                    },
                },
            },
            letterSpacing: {
                eyebrow: '0.12em',
            },
        },
    },

    plugins: [forms],
};
