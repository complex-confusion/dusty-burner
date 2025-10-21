# React/TypeScript Conversion

This plugin has been converted to use React/TypeScript components while maintaining backward compatibility with existing shortcodes.

## Setup

1. Install Node.js dependencies:
   ```bash
   npm install
   ```

2. Build the React components:
   ```bash
   npm run build
   ```

   Or use the build script:
   ```bash
   ./build.sh
   ```

## Development

- `npm run dev` - Start development server with hot reload
- `npm run build` - Build for production
- `npm run preview` - Preview production build

## Architecture

### Components
- **DustDisplay**: Main component that handles all data types
- **DustItem**: Individual item renderer for camps, art, schedule, music
- **ScheduleTabs**: Tabbed interface for schedule display
- **ExportButtons**: CSV/ICS export functionality

### Data Flow
1. PHP shortcodes create React containers with data attributes
2. React components fetch data directly from Dust API
3. Client-side filtering, sorting, and display
4. Export functionality handled in browser

### Key Features
- Direct API calls to Dust endpoints
- Date filtering for schedule/music
- Search functionality
- Accessibility compliant tabs
- CSV/ICS export
- Responsive design
- CSS modules for styling

### Backward Compatibility
- Existing shortcodes continue to work
- Legacy JavaScript remains for compatibility
- Same CSS classes maintained
- Custom events emitted for integration

## File Structure

```
src/
├── components/          # React components
├── hooks/              # Custom hooks (useData)
├── styles/             # CSS modules
├── types/              # TypeScript interfaces
├── utils/              # Utility functions
└── main.tsx           # Entry point
```

## Build Output

The build process creates:
- `dist/dust-events-react.js` - Main JavaScript bundle
- `dist/dust-events-react.css` - Compiled styles

These files are automatically enqueued by the PHP plugin.