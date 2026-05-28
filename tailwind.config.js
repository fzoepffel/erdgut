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
                // Body + UI: clean humanist sans that sits quietly under a serif
                sans: ['Inter', ...defaultTheme.fontFamily.sans],
                // Display + editorial: Instrument Serif carries every headline,
                // pull quote, recipe title and the italicised first name in the
                // hero. Single weight (400) regular + italic per spec — the
                // page never goes bold.
                display: ['"Instrument Serif"', ...defaultTheme.fontFamily.serif],
                editorial: ['"Instrument Serif"', ...defaultTheme.fontFamily.serif],
                // Mono — eyebrow + every piece of metadata (Box №, KW 22, ZEIT,
                // weekday stamps, status pills). JetBrains Mono at 0.18em tracking
                // is the "printed almanac" voice from the design spec.
                mono: ['"JetBrains Mono"', ...defaultTheme.fontFamily.mono],
                // Work Sans — used on the coming-soon marketing page
                work: ['"Work Sans"', ...defaultTheme.fontFamily.sans],
            },
            colors: {
                // Primary brand tokens
                brand: {
                    // Bright cream — primary page background. Reads as warm
                    // off-white printed paper.
                    cream: '#faf4e1',
                    // Slightly darker tinted cream — used for the producer-
                    // of-the-week paper band so it visually steps forward as
                    // a "special sideinfo" inside the otherwise cream page,
                    // and for image placeholder rectangles so empty product/
                    // recipe slots blend with the editorial palette.
                    paper: '#f2ead5',
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
                // Matches the spec: mono eyebrows + metadata labels read as
                // typeset annotations, not UI labels. 0.18em is wide enough
                // to make the spacing intentional without going caps-locked.
                eyebrow: '0.18em',
            },
        },
    },

    plugins: [forms],
};
