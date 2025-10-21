import { DustItem, DataType } from '../types';

const API_BASE_URL = 'https://data.dust.events/';

export const fetchDustData = async (type: DataType, eventName: string): Promise<DustItem[]> => {
  const url = `${API_BASE_URL}${eventName}/${type}.json`;
  
  const response = await fetch(url, {
    headers: {
      'User-Agent': 'LunaCode Display Dust Data WordPress Plugin'
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
  return `${API_BASE_URL}${imagePath}`;
};

export const parsePinCoordinates = (pinString?: string) => {
  if (!pinString) return null;
  
  try {
    return JSON.parse(pinString);
  } catch {
    return null;
  }
};