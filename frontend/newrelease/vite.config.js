import { resolve } from 'node:path';
import { defineConfig } from 'vite';
import react from '@vitejs/plugin-react';

export default defineConfig({
  plugins: [react()],
  base: '/newrelease/',
  build: {
    outDir: resolve(__dirname, '../../public_html/newrelease'),
    emptyOutDir: true,
  },
});

