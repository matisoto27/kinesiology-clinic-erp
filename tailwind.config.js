/** @type {import('tailwindcss').Config} */
export default {
    content: [
        './resources/**/*.blade.php',
        './resources/**/*.js',
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
    ],
    theme: {
        extend: {
            fontFamily: {
                sans: ['Instrument Sans', 'ui-sans-serif', 'system-ui', 'sans-serif', 'Apple Color Emoji', 'Segoe UI Emoji', 'Segoe UI Symbol', 'Noto Color Emoji'],
                lato: ['Lato', 'sans-serif'],
            },
            spacing: {
                22: '5.5rem',
                25: '6.25rem',
                26: '6.5rem',
            },
            width: {
                xs: '20rem',
                md: '28rem',
            },
            maxWidth: {
                'screen-3xl': '1920px',
            },
            zIndex: {
                60: '60',
            },
        },
    },
    plugins: [],
    safelist: [
        'bg-[#3A8F8E]',
        'bg-[#6BA9A9]',
        'bg-[#F5D500]',
        'hover:bg-[#F5D500]',
        'rounded-b-none',
        'rounded-b-xl',
        'rounded-b-md',
        'border-red-500',
        'border-2',
        'focus:ring-red-500',
        'text-red-600',
        'focus:ring-green-500',
        'hidden',
    ],
};
