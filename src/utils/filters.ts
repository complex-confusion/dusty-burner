import { DustItem } from '../types';
import { parseISO, isAfter, isBefore, isValid } from 'date-fns';

export const filterByDateRange = (
  data: DustItem[],
  startDate?: string,
  endDate?: string
): DustItem[] => {
  if (!startDate && !endDate) return data;

  return data.filter(item => {
    if (!item.occurrence?.start_time) return true;

    try {
      const eventDate = parseISO(item.occurrence.start_time);
      if (!isValid(eventDate)) return true;

      if (startDate) {
        const start = parseISO(startDate);
        if (isValid(start) && isBefore(eventDate, start)) return false;
      }

      if (endDate) {
        const end = parseISO(endDate);
        if (isValid(end) && isAfter(eventDate, end)) return false;
      }

      return true;
    } catch {
      return true;
    }
  });
};

export const sortData = (data: DustItem[], type: string): DustItem[] => {
  const sorted = [...data];

  if (type === 'camps' || type === 'art') {
    return sorted.sort((a, b) => {
      const nameA = a.name || '';
      const nameB = b.name || '';
      return nameA.localeCompare(nameB);
    });
  }

  if (type === 'schedule' || type === 'music') {
    return sorted.sort((a, b) => {
      const campA = a.camp || a.hosted_by_camp || '';
      const campB = b.camp || b.hosted_by_camp || '';
      const campCompare = campA.localeCompare(campB);
      
      if (campCompare !== 0) return campCompare;
      
      const titleA = a.title || '';
      const titleB = b.title || '';
      return titleA.localeCompare(titleB);
    });
  }

  return sorted;
};

export const searchFilter = (items: DustItem[], searchTerm: string): DustItem[] => {
  if (!searchTerm.trim()) return items;

  const term = searchTerm.toLowerCase();
  return items.filter(item => {
    const name = (item.name || item.title || '').toLowerCase();
    const description = (item.description || '').toLowerCase();
    const camp = (item.camp || item.hosted_by_camp || '').toLowerCase();
    
    return name.includes(term) || description.includes(term) || camp.includes(term);
  });
};