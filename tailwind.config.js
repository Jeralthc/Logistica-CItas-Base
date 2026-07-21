import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';

/** @type {import('tailwindcss').Config} */
export default {
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
        './resources/js/**/*.vue',
    ],

    theme: {
        extend: {
            fontFamily: {
                sans: ['Inter', ...defaultTheme.fontFamily.sans],
            },
            colors: {
                background: '#09090b', // zinc-950
                foreground: '#fafafa', // zinc-50
                card: '#18181b', // zinc-900
                'card-foreground': '#fafafa',
                primary: {
                    DEFAULT: '#6366f1', // indigo-500
                    foreground: '#ffffff',
                    hover: '#4f46e5', // indigo-600
                },
                secondary: {
                    DEFAULT: '#27272a', // zinc-800
                    foreground: '#fafafa',
                },
                accent: {
                    DEFAULT: '#8b5cf6', // violet-500
                    foreground: '#ffffff',
                },
                muted: {
                    DEFAULT: '#27272a',
                    foreground: '#a1a1aa', // zinc-400
                },
                border: '#27272a',
                glass: 'rgba(24, 24, 27, 0.65)', // card background with opacity
            },
            backgroundImage: {
                'gradient-radial': 'radial-gradient(var(--tw-gradient-stops))',
                'gradient-glass': 'linear-gradient(145deg, rgba(255,255,255,0.05) 0%, rgba(255,255,255,0.01) 100%)',
            },
            animation: {
                'fade-in': 'fadeIn 0.5s ease-out',
                'slide-up': 'slideUp 0.6s cubic-bezier(0.16, 1, 0.3, 1)',
                'scale-in': 'scaleIn 0.4s ease-out',
                'pulse-slow': 'pulse 4s cubic-bezier(0.4, 0, 0.6, 1) infinite',
                'float': 'float 6s ease-in-out infinite',
            },
            keyframes: {
                fadeIn: {
                    '0%': { opacity: '0' },
                    '100%': { opacity: '1' },
                },
                slideUp: {
                    '0%': { transform: 'translateY(20px)', opacity: '0' },
                    '100%': { transform: 'translateY(0)', opacity: '1' },
                },
                scaleIn: {
                    '0%': { transform: 'scale(0.95)', opacity: '0' },
                    '100%': { transform: 'scale(1)', opacity: '1' },
                },
                float: {
                    '0%, 100%': { transform: 'translateY(0)' },
                    '50%': { transform: 'translateY(-10px)' },
                }
            },
        },
    },

    plugins: [forms],
};
