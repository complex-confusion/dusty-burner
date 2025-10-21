import React from 'react';
import { DustItem } from '../types';
import styles from '../styles/components.module.css';

interface Props {
  showExportButtons: string;
  eventName: string;
  data: DustItem[];
}

const generateCSV = (data: DustItem[]): string => {
  const headers = ['Title', 'Camp', 'Location', 'Day', 'Start Time', 'End Time', 'Short Time', 'Long Time', 'Brief Time', 'Image URL', 'Categories', 'Description'];
  
  const rows = data.map(event => [
    event.title || '',
    event.camp || '',
    event.location || '',
    event.day || '',
    event.occurrence?.start_time || '',
    event.occurrence?.end_time || '',
    event.occurrence?.short || '',
    event.occurrence?.long || '',
    event.occurrence?.brief || '',
    event.imageUrl || '',
    event.event_type?.label || '',
    (event.description || '').replace(/\n/g, ' ').replace(/"/g, '""')
  ]);

  const csvContent = [headers, ...rows]
    .map(row => row.map(field => `"${field}"`).join(','))
    .join('\n');

  return csvContent;
};

const generateICS = (data: DustItem[]): string => {
  const escapeICS = (text: string): string => {
    return text.replace(/\n/g, '\\n').replace(/,/g, '\\,').replace(/;/g, '\\;');
  };

  let ics = 'BEGIN:VCALENDAR\r\n';
  ics += 'VERSION:2.0\r\n';
  ics += 'PRODID:-//LunaCode Display Dust Data//Schedule Export//EN\r\n';
  ics += 'CALSCALE:GREGORIAN\r\n';

  data.forEach(event => {
    ics += 'BEGIN:VEVENT\r\n';
    ics += `UID:${event.uid || Math.random().toString(36)}@dust.events\r\n`;
    ics += `SUMMARY:${escapeICS(event.title || '')}\r\n`;
    
    if (event.description) {
      ics += `DESCRIPTION:${escapeICS(event.description)}\r\n`;
    }
    
    if (event.location) {
      ics += `LOCATION:${escapeICS(event.location)}\r\n`;
    }
    
    if (event.occurrence?.start_time) {
      const startTime = new Date(event.occurrence.start_time);
      ics += `DTSTART:${startTime.toISOString().replace(/[-:]/g, '').split('.')[0]}Z\r\n`;
      
      if (event.occurrence.end_time) {
        const endTime = new Date(event.occurrence.end_time);
        ics += `DTEND:${endTime.toISOString().replace(/[-:]/g, '').split('.')[0]}Z\r\n`;
      } else {
        const endTime = new Date(startTime.getTime() + 60 * 60 * 1000);
        ics += `DTEND:${endTime.toISOString().replace(/[-:]/g, '').split('.')[0]}Z\r\n`;
      }
    }
    
    ics += 'END:VEVENT\r\n';
  });

  ics += 'END:VCALENDAR\r\n';
  return ics;
};

const downloadFile = (content: string, filename: string, mimeType: string) => {
  const blob = new Blob([content], { type: mimeType });
  const url = URL.createObjectURL(blob);
  const link = document.createElement('a');
  link.href = url;
  link.download = filename;
  document.body.appendChild(link);
  link.click();
  document.body.removeChild(link);
  URL.revokeObjectURL(url);
};

export const ExportButtons: React.FC<Props> = ({ showExportButtons, eventName, data }) => {
  if (showExportButtons === 'false' || showExportButtons === 'none' || !showExportButtons) {
    return null;
  }

  const handleCSVExport = () => {
    const csv = generateCSV(data);
    downloadFile(csv, `${eventName}-schedule.csv`, 'text/csv');
  };

  const handleICSExport = () => {
    const ics = generateICS(data);
    downloadFile(ics, `${eventName}-schedule.ics`, 'text/calendar');
  };

  const showCSV = showExportButtons === 'all' || showExportButtons === 'true' || showExportButtons.includes('csv');
  const showICS = showExportButtons === 'all' || showExportButtons === 'true' || showExportButtons.includes('ics');

  if (showExportButtons.includes('none')) {
    return null;
  }

  return (
    <div className={styles.exportButtons}>
      {showICS && (
        <button 
          className={styles.exportButton}
          onClick={handleICSExport}
          aria-label="Export schedule to calendar file"
          type="button"
        >
          📅 Export to Calendar
        </button>
      )}
      {showCSV && (
        <button 
          className={styles.exportButton}
          onClick={handleCSVExport}
          aria-label="Export schedule to CSV file"
          type="button"
        >
          📊 Export to CSV
        </button>
      )}
    </div>
  );
};