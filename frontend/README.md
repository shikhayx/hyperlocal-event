# NearU Frontend

The frontend for NearU is a React 19 application with Server-Side Rendering (SSR), built with TypeScript, Vite, Mantine UI, and Lingui for internationalization.

## Tech Stack

- **Framework**: React 19 + Vite SSR
- **UI Components**: Mantine UI (`@mantine/core`, `@mantine/hooks`, `@mantine/form`)
- **State & Data Fetching**: TanStack React Query
- **Styling**: SCSS modules + Mantine CSS variables
- **Internationalization**: LinguiJS
- **Icons**: Tabler Icons

## Development

```bash
# Start the SSR dev server
yarn dev:ssr

# Start the CSR dev server (optional)
yarn dev:csr

# Extract and compile translation strings
yarn messages:extract
yarn messages:compile
```

## Branding & Configuration

The application branding is dynamically driven by environment variables:
- `VITE_APP_NAME` (Default: `NearU`)
- `VITE_APP_LOGO_DARK` (Default: `/logos/nearu-horizontal-light.svg`)
- `VITE_APP_LOGO_LIGHT` (Default: `/logos/nearu-text-dark.svg`)
- `VITE_APP_FAVICON` (Default: `/manifest-icons/favicon.svg`)
