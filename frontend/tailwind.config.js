/** @type {import('tailwindcss').Config} */
export default {
  content: ['./index.html', './src/**/*.{ts,tsx}'],
  theme: {
    extend: {
      colors: {
        // هوية المكتب — كحلي (Navy) هو اللون الأساسي (brand) ليتبنّاه كل المكوّنات القائمة تلقائياً.
        brand: {
          50: '#eef2fb',
          100: '#d7e0f2',
          200: '#adc0e4',
          500: '#1e3a6e',
          600: '#16305c',
          700: '#0f2547',
          800: '#0a1c38',
        },
        // ذهبي (Gold) — لون التمييز للهوية (شعار/تفاصيل/حالات مهمّة).
        gold: {
          50: '#fbf6e9',
          100: '#f4e7c3',
          400: '#d4af37',
          500: '#c9a227',
          600: '#a8851d',
        },
      },
      fontFamily: {
        // خط عربي مناسب مع تدرّج نظامي آمن دون اعتماد على جلب خارجي.
        sans: [
          'Tajawal',
          'Noto Sans Arabic',
          'Segoe UI',
          'Tahoma',
          'Arial',
          'system-ui',
          'sans-serif',
        ],
      },
    },
  },
  plugins: [],
}
