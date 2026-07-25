import { resolve } from 'node:path'

import react from '@vitejs/plugin-react'
import { defineConfig } from 'vite'

export default defineConfig(({ mode }) => {
  const isFilesBuild = mode === 'files'
  const entryName = isFilesBuild ? 'files' : 'admin'

  return {
    plugins: [react()],
    define: {
      'process.env.NODE_ENV': JSON.stringify('production'),
    },
    build: {
      outDir: '.',
      emptyOutDir: false,
      cssCodeSplit: false,
      minify: 'esbuild',
      sourcemap: false,
      lib: {
        entry: resolve(
          import.meta.dirname,
          isFilesBuild ? 'src/files-lockdown.ts' : 'src/main.tsx',
        ),
        name: isFilesBuild ? 'UserLockdownFiles' : 'UserLockdownAdmin',
        formats: ['iife'],
        fileName: () => `js/user-lockdown-${entryName}.js`,
        cssFileName: `user-lockdown-${entryName}`,
      },
      rollupOptions: {
        output: {
          assetFileNames: 'css/[name][extname]',
          inlineDynamicImports: true,
        },
      },
    },
  }
})
