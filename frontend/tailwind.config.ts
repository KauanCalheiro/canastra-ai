import type { Config } from 'tailwindcss'

export default <Partial<Config>>{
  content: [
    './app/components/**/*.vue',
    './app/pages/**/*.vue',
    './app/app.vue'
  ],
  theme: {
    extend: {
      colors: {
        ink: '#0e0f0c',
        primary: '#9fe870',
        'primary-active': '#cdffad',
        'primary-neutral': '#7ac95a',
        'primary-pale': '#e9f9dd',
        'canvas-soft': '#e8ebe6',
        body: '#454745',
        mute: '#717570',
        positive: '#2ead4b',
        negative: '#d03238',
        'ink-deep': '#163300',
        'card-red': '#d03238',
        'card-black': '#0e0f0c'
      },
      borderRadius: {
        pill: '9999px',
        xl: '24px',
        card: '16px',
        md: '12px',
        sm: '8px',
        'card-face': '6px'
      },
      fontFamily: {
        display: ['Manrope', 'sans-serif'],
        body: ['Inter', 'sans-serif']
      }
    }
  }
}
