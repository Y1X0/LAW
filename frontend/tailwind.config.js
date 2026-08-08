/** @type {import('tailwindcss').Config} */
export default {
  content: ['./index.html', './src/**/*.{ts,tsx}'],
  theme: {
    extend: {
      colors: {
        // هوية المكتب — فحمي داكن (Charcoal) هو اللون الأساسي (brand): يتبنّاه كل المكوّنات
        // القائمة تلقائياً (أزرار/عناوين/Sidebar). إحساس مؤسسي رصين (Clio/Thomson Reuters)
        // بلا لمعان — أوف وايت مسيطر، فحمي للبنية، ذهب للمسات فقط. تدرّج محايد بميل رمادي طفيف.
        brand: {
          50: '#f4f5f7', //  خلفيات نشطة خفيفة جداً
          100: '#e8eaee',
          200: '#ccd1d9',
          500: '#3a4252', //  نص/لمسة ثانوية
          600: '#242a35',
          700: '#171b22', //  الأساسي — أزرار
          800: '#111318', //  الأغمق — Sidebar
        },
        // ذهبي مطفأ عستي (Muted Gold) — تمييز فاخر ≤10٪: عنصر نشط · حالة حرِجة · أيقونة رئيسية.
        // ممنوع كخلفية عامة أو نص واسع. درجة راقية غير لامعة (#C5A059).
        gold: {
          50: '#f7f2e6',
          100: '#ecdfbf',
          400: '#c5a059', //  الذهب الأساسي (كان لامعاً #d4af37)
          500: '#b8923f',
          600: '#9c7a2e',
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
      // ظلال هادئة فاخرة (Premium elevation) — مربوطة بمتغيّرات CSS.
      boxShadow: {
        card: 'var(--shadow-card)',
        'card-hover': 'var(--shadow-card-hover)',
        header: 'var(--shadow-header)',
      },
      borderRadius: {
        xl: '0.875rem',
        '2xl': '1.125rem',
      },
    },
  },
  plugins: [],
}
