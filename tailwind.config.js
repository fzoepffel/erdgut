import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';

/** @type {import('tailwindcss').Config} */
export default {
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
    ],

    // Pricing-calendar day shades live in only one view (a PHP class map), so
    // guarantee they survive every build regardless of content scanning.
    safelist: [
        'bg-green-200', 'bg-green-400', 'bg-green-600', 'bg-green-800',
        'hover:bg-green-300', 'hover:bg-green-500', 'hover:bg-green-700', 'hover:bg-green-900',
        'text-green-900', 'text-green-950',
        'bg-red-200', 'bg-red-400', 'bg-red-600', 'bg-red-800',
        'hover:bg-red-300', 'hover:bg-red-500', 'hover:bg-red-700', 'hover:bg-red-900',
        'text-red-900', 'text-red-950',
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
                // Shop storefront palette — exact OKLCH tokens from the
                // approved shop prototype. Kept as a dedicated namespace so the
                // storefront matches the prototype 1:1 without disturbing the
                // brand tokens the rest of the site relies on.
                shop: {
                    background: 'oklch(96.5% 0.025 90)', // --cream / --background
                    foreground: 'oklch(22% 0.04 140)', // --ink / --foreground
                    primary: 'oklch(32% 0.08 140)', // --primary (deep moss)
                    'primary-hover': 'oklch(27% 0.08 140)', // hover:bg-primary/90 equiv
                    'primary-foreground': 'oklch(96.5% 0.025 90)', // cream on green
                    border: 'oklch(85% 0.025 90)', // --border / --input
                    muted: 'oklch(45% 0.03 110)', // --muted-foreground
                    paper: 'oklch(94% 0.03 88)', // --paper
                    card: 'oklch(98.5% 0.012 90)', // --card
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
