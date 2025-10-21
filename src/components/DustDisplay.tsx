import React, { useMemo } from 'react';
import { ComponentProps, DataType } from '../types';
import { useData } from '../hooks/useData';
import { DustItem } from './DustItem';
import { ExportButtons } from './ExportButtons';
import { ScheduleTabs } from './ScheduleTabs';
import styles from '../styles/components.module.css';

interface Props extends ComponentProps {
  type: DataType;
}

export const DustDisplay: React.FC<Props> = ({
  type,
  eventName,
  layout = 'grid',
  showCoordinates = false,
  showImages = true,
  perPage = -1,
  showExportButtons = 'false',
  display = 'all',
  startDate,
  endDate
}) => {
  const { data, loading, error, filteredData, searchTerm, setSearchTerm } = useData(
    type,
    eventName,
    startDate,
    endDate
  );

  const displayData = useMemo(() => {
    const items = filteredData.length > 0 || searchTerm ? filteredData : data;
    return perPage > 0 ? items.slice(0, perPage) : items;
  }, [filteredData, data, searchTerm, perPage]);

  const handleItemClick = (item: any) => {
    // Emit custom event for compatibility with existing JavaScript
    const event = new CustomEvent('dustItemClicked', {
      detail: {
        uid: item.uid,
        name: item.name || item.title,
        type,
        element: null
      }
    });
    document.dispatchEvent(event);
  };

  if (!eventName) {
    return <p>Please configure the event name in the plugin settings.</p>;
  }

  if (loading) {
    return <div className={styles.loading}>Loading {type}...</div>;
  }

  if (error) {
    return <div className={styles.error}>Error loading {type} data: {error}</div>;
  }

  if (data.length === 0) {
    return <div className={styles.noResults}>No {type} found.</div>;
  }

  // Schedule with tabs display
  if (type === 'schedule' && display === 'tabs') {
    return (
      <div className={styles.container}>
        {showExportButtons !== 'false' && showExportButtons !== 'none' && (
          <ExportButtons 
            showExportButtons={showExportButtons}
            eventName={eventName}
            data={displayData}
          />
        )}
        <ScheduleTabs
          data={displayData}
          showImages={showImages}
          layout={layout}
          onItemClick={handleItemClick}
        />
      </div>
    );
  }

  return (
    <div className={styles.container}>
      {/* Search */}
      <div className={styles.searchContainer}>
        <input
          type="text"
          className={styles.searchInput}
          placeholder={`Search ${type}...`}
          value={searchTerm}
          onChange={(e) => setSearchTerm(e.target.value)}
        />
      </div>

      {/* Export buttons for schedule */}
      {type === 'schedule' && showExportButtons !== 'false' && showExportButtons !== 'none' && (
        <ExportButtons 
          showExportButtons={showExportButtons}
          eventName={eventName}
          data={displayData}
        />
      )}

      {/* Results count */}
      {searchTerm && (
        <div className={styles.field}>
          Showing {displayData.length} of {data.length} {type}
        </div>
      )}

      {/* Items */}
      {displayData.length === 0 ? (
        <div className={styles.noResults}>
          {searchTerm ? `No ${type} found matching "${searchTerm}"` : `No ${type} found.`}
        </div>
      ) : (
        <div className={`${layout === 'grid' ? styles.grid : styles.list}`}>
          {displayData.map(item => (
            <DustItem
              key={item.uid}
              item={item}
              type={type}
              showImages={showImages}
              showCoordinates={showCoordinates}
              layout={layout}
              onClick={handleItemClick}
            />
          ))}
        </div>
      )}
    </div>
  );
};