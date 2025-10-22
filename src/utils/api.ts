import { DustItem, DataType } from '../types';

// Get WordPress REST API URL from global object
const getRestUrl = (): string => {
  // @ts-ignore - WordPress global object
  return window.dust_events_ajax?.rest_url || '/wp-json/dust-events/v1/';
};

export const fetchDustData = async (type: DataType, eventName: string, noCache = false): Promise<DustItem[]> => {
  const restUrl = getRestUrl();
  const params = new URLSearchParams({
    event_name: eventName
  });
  
  if (noCache) {
    params.append('no_cache', '1');
  }
  
  const url = `${restUrl}data/${type}?${params.toString()}`;
  
  const response = await fetch(url, {
    headers: {
      'Content-Type': 'application/json'
    }
  });

  if (!response.ok) {
    throw new Error(`API returned status code: ${response.status}`);
  }

  const data = await response.json();
  return Array.isArray(data) ? data : [];
};

export const getImageUrl = (imagePath?: string): string | null => {
  if (!imagePath) return null;
  if (imagePath.includes('://')) return imagePath;
  return `https://data.dust.events/${imagePath}`;
};

export const parsePinCoordinates = (pinString?: string) => {
  if (!pinString) return null;
  
  try {
    return JSON.parse(pinString);
  } catch {
    return null;
  }
};