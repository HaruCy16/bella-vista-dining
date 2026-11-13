/** @type {import('tailwindcss').Config} */
// Bella Vista design tokens. Every size, radius and colour used by the site lives
// here; src/input.css uses only these names (no one-off pixel values).
// Text/background pairs were contrast-checked against WCAG AA (4.5:1 for body text).
module.exports = {
  content: ['./index.php', './public/**/*.php', './includes/**/*.php', './admin/**/*.php', './public/assets/js/**/*.js'],
  future: {
    hoverOnlyWhenSupported: true, // hover styles only on devices that can hover (no sticky hover on tap)
  },
  theme: {
    extend: {
      // Palette from the Figma designs (website, customer mobile, staff). The five core
      // colours are CSS variables (src/input.css) so phones and the admin get their own
      // shades of them. Values changed for contrast are noted; everything else is as designed.
      colors: {
        canvas: 'rgb(var(--canvas) / <alpha-value>)', // page background: web #F6F3EB, mobile/staff #F7F1E5
        paper: 'rgb(var(--paper) / <alpha-value>)',   // cards, forms: web #FFFEFA, mobile/staff #FFFDF8
        ink: 'rgb(var(--ink) / <alpha-value>)',       // body text: web #263D32, mobile #29352F, staff #2B2B2B
        forest: 'rgb(var(--forest) / <alpha-value>)', // headings, primary buttons, dark bands: web #203F32, mobile/staff #1A3721
        line: 'rgb(var(--line) / <alpha-value>)',     // dividers, card outlines: web #DADDD1, mobile #DCDDCF, staff #DDDCCF
        muted: '#657068',    // secondary text (design #73796E is 4.0:1; this is the mobile shade, 4.65:1)
        pine: '#2F5D50',     // icons, selected states, quantity controls (6.8:1)
        clay: '#99583C',     // small uppercase labels, history dates (design #A96143 darkened to pass 4.5:1)
        rust: '#B35F37',     // mobile hero button (design #C4693D darkened so white text passes)
        mint: '#E6ECDC',     // menu band, active badges, context pills
        mist: '#EEF0E9',     // inactive category tabs, quantity editing
        sage: '#E1E8DA',     // quantity value, image placeholders
        'qty-btn': '#F0F0EA',
        'pay-on': '#EDF2EA', // selected payment option
        sand: '#F1E6D5',     // policy and arrival-reminder panels
        cream: '#D3DCCD',    // text on dark bands (8.2:1 on forest)
        gold: '#C9A24B',     // "Private" seating badge
        butter: '#FBF1D8',   // pre-order status badge
        grove: '#1A3721',    // dark brand panels
        olive: '#286343',    // positive text
        danger: '#B3261E',
        'danger-ink': '#A13831', 'danger-bg': '#FBEDEA', 'danger-dot': '#C2453D',
        'ok-ink': '#286343', 'ok-bg': '#EDF1E8', 'ok-dot': '#4C9A6A',
        'warn-ink': '#805D0A', 'warn-bg': '#F8EFD7', 'warn-dot': '#E0A100',
        'seated-ink': '#356483', 'seated-bg': '#EAF1F7', 'seated-dot': '#5B8DB8',
        'idle-ink': '#5D625B', 'idle-bg': '#EEEFEA', 'idle-dot': '#8A8A8A',
        overlay: '#16382B',  // hero photo gradient
        deep: '#7A3E2A',     // legacy name still used by inner pages
      },
      fontFamily: {
        serif: ['Newsreader', 'Georgia', 'serif'],
        sans: ['system-ui', '-apple-system', 'Segoe UI', 'Roboto', 'Helvetica Neue', 'Arial', 'sans-serif'],
      },
      // Type scale. Display sizes get tight leading and negative tracking; small sizes open up slightly.
      fontSize: {
        display: ['clamp(2.5rem, 4.6vw, 4.25rem)', { lineHeight: '1.02', letterSpacing: '-0.025em' }],
        title: ['clamp(2rem, 3.4vw, 3rem)', { lineHeight: '1.06', letterSpacing: '-0.02em' }],
        'heading-lg': ['2rem', { lineHeight: '1.12', letterSpacing: '-0.015em' }],
        heading: ['1.5rem', { lineHeight: '1.2', letterSpacing: '-0.01em' }],
        subheading: ['1.25rem', { lineHeight: '1.3', letterSpacing: '-0.005em' }],
        lead: ['1.125rem', { lineHeight: '1.6' }],
        body: ['1rem', { lineHeight: '1.6' }],
        small: ['0.875rem', { lineHeight: '1.5', letterSpacing: '0.005em' }],
        caption: ['0.8125rem', { lineHeight: '1.4', letterSpacing: '0.01em' }],
      },
      letterSpacing: {
        label: '0.04em',     // short uppercase labels
      },
      borderRadius: {
        thumb: '0.5rem',     // small images
        ctl: '0.625rem',     // buttons, inputs, list rows
        card: '1rem',        // cards and panels
        panel: '1.25rem',    // modal, hero image, large surfaces
      },
      maxWidth: {
        page: '75rem',       // 1200px content width
        measure: '34rem',    // comfortable paragraph width
      },
      transitionTimingFunction: {
        out: 'cubic-bezier(0.23, 1, 0.32, 1)',
      },
    },
  },
  plugins: [],
}
