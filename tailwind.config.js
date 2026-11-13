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
      colors: {
        canvas: '#F6F2E9',   // page background
        paper: '#FCFAF5',    // raised surfaces (cards, modal, booking bar)
        ink: '#23251C',      // primary text (13.9:1 on canvas)
        muted: '#5E6052',    // secondary text (5.75:1 on canvas)
        clay: '#9A3F2A',     // accent text and links on light (6.0:1 on canvas)
        rust: '#B5523C',     // primary buttons, white label (4.96:1)
        olive: '#4A5E28',    // positive state, open status (6.4:1 on canvas)
        grove: '#2F3A1E',    // dark band background
        cream: '#E9D7B9',    // text on grove (8.5:1)
        sage: '#DDE2D0',
        line: 'rgba(35, 37, 28, 0.14)',
        danger: '#B42318',
        'danger-ink': '#8C1D13',
        'warn-ink': '#7A4B0C',
        'ok-ink': '#2F3D17',
        deep: '#7A3E2A',     // legacy name still used by inner pages
        forest: '#1F3A2C',   // primary actions, dark bands (white text 12.9:1)
        pine: '#3A5D4B',     // selected states, section labels (6.9:1 on canvas)
        mint: '#E3EADC',     // context pills, light green bands
        gold: '#E2C98F',     // "Private" badge (ink text)
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
