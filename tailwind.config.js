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
            colors: {
                cream: '#ECE0DA',
                blush: '#F1C8CB',
                petal: '#F6B0BB',
                sage: '#9CAD8C',
                moss: '#7E8C69',
                ink: '#374030',
                paper: '#FBF7F4',
            },
            fontFamily: {
                sans: ['Figtree', ...defaultTheme.fontFamily.sans],
                display: ['"Cormorant Garamond"', ...defaultTheme.fontFamily.serif],
            },
            keyframes: {
                float: {
                    '0%, 100%': { transform: 'translateY(0)' },
                    '50%': { transform: 'translateY(-12px)' },
                },
                fadeUp: {
                    '0%': { opacity: '0', transform: 'translateY(24px)' },
                    '100%': { opacity: '1', transform: 'translateY(0)' },
                },
                marquee: {
                    '0%': { transform: 'translateX(0)' },
                    '100%': { transform: 'translateX(-50%)' },
                },
            },
            animation: {
                float: 'float 7s ease-in-out infinite',
                'float-slow': 'float 10s ease-in-out infinite',
                'fade-up': 'fadeUp 0.9s cubic-bezier(0.2, 0.7, 0.2, 1) both',
                marquee: 'marquee 38s linear infinite',
            },
        },
    },

    plugins: [forms],
};
