import { defineConfig } from 'vite'
import react from '@vitejs/plugin-react'

export default defineConfig({
  plugins: [react()],
  build: {
    outDir: 'dist',
    rollupOptions: {
      input: {
        main: 'src/main.tsx'
      },
      output: {
        entryFileNames: 'dust-events-react.js',
        chunkFileNames: 'dust-events-react-[hash].js',
        assetFileNames: 'dust-events-react.[ext]'
      }
    }
  },
  define: {
    'process.env.NODE_ENV': JSON.stringify(process.env.NODE_ENV || 'production')
  }
})