import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';

/** @type {import('tailwindcss').Config} */
export default {
    darkMode: 'class',

    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
    ],

    theme: {
        extend: {
            fontFamily: {
                sans: ['Poppins', ...defaultTheme.fontFamily.sans],
            },
            colors: {
                brand: {
                    50: '#FBEAEA',
                    100: '#F6D4D4',
                    200: '#EDAAAA',
                    300: '#E27E7E',
                    400: '#DA5E5E',
                    500: '#E24545',
                    600: '#CF1D1D',
                    700: '#A81616',
                    800: '#8E1212',
                    900: '#6E0E0E',
                    950: '#4A0909',
                },
                // Escala neutra general (texto, fondos y bordes de las pantallas ya
                // existentes). Ancla en los mismos valores de texto de la guía de
                // estilos corporativa (ink-900 = ink.default, etc.); los pasos
                // intermedios toman la escala `gray` por defecto de Tailwind, que es
                // la misma que la guía documenta como `neutral.gray`.
                ink: {
                    50: '#f9fafb',
                    100: '#f3f4f6',
                    200: '#e5e7eb',
                    300: '#d1d5db',
                    400: '#9ca3af',
                    500: '#6b7280',
                    600: '#4b5563',
                    700: '#374151',
                    800: '#1f2937',
                    900: '#111111',
                    950: '#030712',
                    secondary: '#616161',
                    muted: '#3C3C3C',
                },
                canvas: {
                    DEFAULT: '#FFFFFF',
                    dark: '#121212',
                },
                surface: {
                    DEFAULT: '#FFFFFF',
                    alt: '#F7F4F4',
                    dark: '#1C1C1C',
                },
                line: {
                    DEFAULT: '#EDE8EC',
                },
                success: {
                    DEFAULT: '#1A7A42',
                    soft: '#E6F4EC',
                    dark: '#2FAE68',
                },
                warning: {
                    DEFAULT: '#E58A45',
                    soft: '#FDF0E6',
                    text: '#9C5319',
                },
                danger: {
                    DEFAULT: '#7A1E1E',
                    soft: '#F6E9E9',
                    dark: '#D96E6E',
                    hover: '#5E1717',
                    active: '#431010',
                },
                accent: {
                    DEFAULT: '#E58A45',
                    soft: '#FFD7A7',
                },
                cold: {
                    DEFAULT: '#D5DFF7',
                },
            },
            boxShadow: {
                card: '0 1px 2px 0 rgb(15 23 42 / 0.04), 0 1px 3px 0 rgb(15 23 42 / 0.06)',
                'card-lg': '0 4px 12px -2px rgb(15 23 42 / 0.08), 0 2px 6px -2px rgb(15 23 42 / 0.05)',
            },
        },
    },

    plugins: [forms],
};
