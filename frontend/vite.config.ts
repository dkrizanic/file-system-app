import react from '@vitejs/plugin-react'
import { defineConfig } from 'vite'

// Dev proxy: inside the compose network the app is reachable as http://app:80;
// running against a local compose stack from the host targets the dev port.
const apiProxyTarget = process.env.API_PROXY_TARGET ?? 'http://localhost:8081'

export default defineConfig({
  plugins: [react()],
  server: {
    host: true,
    proxy: {
      '/api': {
        target: apiProxyTarget,
      },
    },
  },
})
