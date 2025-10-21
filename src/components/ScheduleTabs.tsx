import React, { useState } from 'react';
import { DustItem } from '../types';
import { DustItem as DustItemComponent } from './DustItem';
import styles from '../styles/components.module.css';

interface TabData {
  label: string;
  events: DustItem[];
}

interface Props {
  data: DustItem[];
  showImages: boolean;
  layout: 'grid' | 'list';
  onItemClick?: (item: DustItem) => void;
}

const organizeScheduleByDays = (data: DustItem[]): Record<string, TabData> => {
  const tabs: Record<string, TabData> = {};
  const everydayEvents: DustItem[] = [];
  const dayEvents: Record<string, DustItem[]> = {};
  const titleDayCount: Record<string, Set<string>> = {};

  // Count days per title
  data.forEach(event => {
    const title = event.title || '';
    const day = event.day || '';
    
    if (title && day) {
      if (!titleDayCount[title]) {
        titleDayCount[title] = new Set();
      }
      titleDayCount[title].add(day);
    }
  });

  // Categorize events
  data.forEach(event => {
    const title = event.title || '';
    const day = event.day || '';
    
    // Check if it's an everyday event
    let isEveryday = false;
    if (title) {
      const dailyKeywords = ['daily', 'every day', 'everyday', 'all days'];
      const titleLower = title.toLowerCase();
      const descLower = (event.description || '').toLowerCase();
      
      isEveryday = dailyKeywords.some(keyword => 
        titleLower.includes(keyword) || descLower.includes(keyword)
      );
      
      // Also check if appears on 2+ days
      if (!isEveryday && titleDayCount[title] && titleDayCount[title].size >= 2) {
        isEveryday = true;
      }
    }
    
    if (isEveryday) {
      everydayEvents.push(event);
    }
    
    if (day) {
      if (!dayEvents[day]) {
        dayEvents[day] = [];
      }
      dayEvents[day].push(event);
    }
  });

  // Add repeating tab
  if (everydayEvents.length > 0) {
    tabs.repeating = {
      label: 'Repeating',
      events: sortScheduleEvents(everydayEvents)
    };
  }

  // Add day tabs
  Object.entries(dayEvents).forEach(([day, events]) => {
    const tabKey = day.toLowerCase().replace(/\s+/g, '_');
    tabs[tabKey] = {
      label: day,
      events: sortScheduleEvents(events)
    };
  });

  return tabs;
};

const sortScheduleEvents = (events: DustItem[]): DustItem[] => {
  return [...events].sort((a, b) => {
    const aAllDay = a.occurrence?.all_day;
    const bAllDay = b.occurrence?.all_day;
    
    if (aAllDay && !bAllDay) return -1;
    if (!aAllDay && bAllDay) return 1;
    
    const aTime = a.occurrence?.start_time || '';
    const bTime = b.occurrence?.start_time || '';
    
    if (aTime && bTime) {
      const timeCompare = aTime.localeCompare(bTime);
      if (timeCompare !== 0) return timeCompare;
    }
    
    return (a.title || '').localeCompare(b.title || '');
  });
};

export const ScheduleTabs: React.FC<Props> = ({ 
  data, 
  showImages, 
  layout, 
  onItemClick 
}) => {
  const [tabs] = useState(() => organizeScheduleByDays(data));
  const [activeTab, setActiveTab] = useState(() => Object.keys(tabs)[0] || '');

  const handleTabClick = (tabKey: string) => {
    setActiveTab(tabKey);
  };

  const handleKeyDown = (e: React.KeyboardEvent, tabKey: string) => {
    const tabKeys = Object.keys(tabs);
    const currentIndex = tabKeys.indexOf(activeTab);
    let newIndex = currentIndex;

    switch (e.key) {
      case 'ArrowLeft':
      case 'ArrowUp':
        e.preventDefault();
        newIndex = currentIndex > 0 ? currentIndex - 1 : tabKeys.length - 1;
        break;
      case 'ArrowRight':
      case 'ArrowDown':
        e.preventDefault();
        newIndex = currentIndex < tabKeys.length - 1 ? currentIndex + 1 : 0;
        break;
      case 'Home':
        e.preventDefault();
        newIndex = 0;
        break;
      case 'End':
        e.preventDefault();
        newIndex = tabKeys.length - 1;
        break;
      case 'Enter':
      case ' ':
        e.preventDefault();
        setActiveTab(tabKey);
        return;
    }

    if (newIndex !== currentIndex) {
      setActiveTab(tabKeys[newIndex]);
    }
  };

  if (Object.keys(tabs).length === 0) {
    return <div className={styles.noResults}>No schedule data available</div>;
  }

  return (
    <div className={styles.tabsContainer}>
      <div className={styles.tabNav} role="tablist" aria-label="Schedule by day">
        {Object.entries(tabs).map(([tabKey, tabData]) => (
          <button
            key={tabKey}
            className={`${styles.tabButton} ${activeTab === tabKey ? styles.tabButtonActive : ''}`}
            role="tab"
            aria-selected={activeTab === tabKey}
            aria-controls={`panel-${tabKey}`}
            id={`tab-${tabKey}`}
            tabIndex={activeTab === tabKey ? 0 : -1}
            onClick={() => handleTabClick(tabKey)}
            onKeyDown={(e) => handleKeyDown(e, tabKey)}
          >
            {tabData.label}
          </button>
        ))}
      </div>

      <div className={styles.tabContent}>
        {Object.entries(tabs).map(([tabKey, tabData]) => (
          <div
            key={tabKey}
            className={`${styles.tabPane} ${activeTab === tabKey ? styles.tabPaneActive : ''}`}
            role="tabpanel"
            aria-labelledby={`tab-${tabKey}`}
            id={`panel-${tabKey}`}
            tabIndex={0}
          >
            <div className={`${styles.container} ${layout === 'grid' ? styles.grid : styles.list}`}>
              {tabData.events.map(item => (
                <DustItemComponent
                  key={item.uid}
                  item={item}
                  type="schedule"
                  showImages={showImages}
                  showCoordinates={false}
                  layout={layout}
                  onClick={onItemClick}
                />
              ))}
            </div>
          </div>
        ))}
      </div>
    </div>
  );
};