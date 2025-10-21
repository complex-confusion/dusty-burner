import { createRoot } from 'react-dom/client';
import { DustDisplay } from './components/DustDisplay';
import { ComponentProps, DataType } from './types';

// Global function to initialize React components
declare global {
  interface Window {
    DustEventsReact: {
      init: () => void;
    };
  }
}

const initializeComponents = () => {
  // Find all containers that need React components
  const containers = document.querySelectorAll('[data-dust-react]');
  
  containers.forEach(container => {
    const element = container as HTMLElement;
    const type = element.dataset.dustType as DataType;
    const props: ComponentProps = {
      eventName: element.dataset.eventName || '',
      layout: (element.dataset.layout as 'grid' | 'list') || 'grid',
      showCoordinates: element.dataset.showCoordinates === 'true',
      showImages: element.dataset.showImages !== 'false',
      perPage: parseInt(element.dataset.perPage || '-1'),
      showExportButtons: element.dataset.showExportButtons || 'false',
      display: (element.dataset.display as 'all' | 'tabs') || 'all',
      startDate: element.dataset.startDate,
      endDate: element.dataset.endDate,
      timezone: element.dataset.timezone
    };

    if (type && props.eventName) {
      const root = createRoot(element);
      root.render(
        <DustDisplay
          type={type}
          {...props}
        />
      );
    }
  });
};

// Initialize when DOM is ready
if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', initializeComponents);
} else {
  initializeComponents();
}

// Make available globally for WordPress
window.DustEventsReact = {
  init: initializeComponents
};