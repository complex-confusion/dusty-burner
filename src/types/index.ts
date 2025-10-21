export interface DustItem {
  uid: string;
  name?: string;
  title?: string;
  description?: string;
  imageUrl?: string;
  pin?: string;
  artist?: string;
  camp?: string;
  hosted_by_camp?: string;
  location?: string;
  day?: string;
  occurrence?: {
    start_time?: string;
    end_time?: string;
    short?: string;
    long?: string;
    brief?: string;
    who?: string;
    all_day?: boolean;
  };
  event_type?: {
    label?: string;
  };
  images?: Array<{
    thumbnail_url: string;
  }>;
  camp_type?: string;
  art_type?: string;
}

export type DataType = 'camps' | 'art' | 'schedule' | 'music';
export type Layout = 'grid' | 'list';
export type Display = 'all' | 'tabs';

export interface ComponentProps {
  eventName: string;
  layout?: Layout;
  showCoordinates?: boolean;
  showImages?: boolean;
  perPage?: number;
  showExportButtons?: string;
  display?: Display;
  startDate?: string;
  endDate?: string;
  timezone?: string;
}

export interface PinData {
  lat?: number;
  lng?: number;
  x?: number;
  y?: number;
}

export interface TabData {
  label: string;
  events: DustItem[];
}

export interface DataState {
  data: DustItem[];
  loading: boolean;
  error: string | null;
  filteredData: DustItem[];
  searchTerm: string;
}

export type DataAction =
  | { type: 'FETCH_START' }
  | { type: 'FETCH_SUCCESS'; payload: DustItem[] }
  | { type: 'FETCH_ERROR'; payload: string }
  | { type: 'SET_SEARCH'; payload: string }
  | { type: 'FILTER_DATA'; payload: DustItem[] };