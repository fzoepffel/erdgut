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
                // Body + UI: humanist sans for warmer reading
                sans: ['Manrope', ...defaultTheme.fontFamily.sans],
                // Display: rounded chunky sans matching the new Erdgut wordmark.
                // Unbounded is the best Bunny/Google Fonts match; swap to a self-hosted
                // Tanker/Recoleta if licensed later.
                display: ['Unbounded', 'Tanker', 'Recoleta', ...defaultTheme.fontFamily.serif],
                // Editorial: reserved for long-form quotes and founder letters
                editorial: ['Fraunces', ...defaultTheme.fontFamily.serif],
            },
            colors: {
                // Primary brand tokens
                brand: {
                    cream: '#f1e7d6', // primary page background
                    paper: '#faf5ec', // lighter surface for cards
                    ink: '#2c241c', // deep warm near-black
                    'ink-soft': '#6b5b4c',
                    moss: '#21341d', // primary brand green (= chapter.produce.DEFAULT)
                },
                // Chapter palette from brand guidelines.
                // Dark = ink / label / outline. Soft = surface / divider / soft fill.
                chapter: {
                    produce: { DEFAULT: '#21341d', soft: '#709452' }, // F&V green
                    meat: { DEFAULT: '#ac273b', soft: '#e1b4b6' }, // meat red / pink
                    fish: { DEFAULT: '#1d4485', soft: '#a7d4e3' }, // F&S blue
                    eggdairy: { DEFAULT: '#e46f25', soft: '#e6c0a8' }, // E&D orange
                    bread: { DEFAULT: '#944e22', soft: '#dec1ae' }, // bread brown
                    producers: { DEFAULT: '#a37a2c', soft: '#e6d6b4' }, // producers ochre
                    almanach: { DEFAULT: '#703b46', soft: '#d8c1c8' }, // almanach purple/plum
                },
                // Accents
                accent: {
                    star: '#c53a2b', // triple-star ✱✱✱ red
                },
            },
            letterSpacing: {
                // Tighter than before — catalogue labels are compact, not wide
                eyebrow: '0.08em',
            },
        },
    },

    plugins: [forms],
};
