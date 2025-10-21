import { useReducer, useEffect } from 'react';
import { DataState, DataAction, DataType } from '../types';
import { fetchDustData } from '../utils/api';
import { filterByDateRange, sortData, searchFilter } from '../utils/filters';

const initialState: DataState = {
  data: [],
  loading: false,
  error: null,
  filteredData: [],
  searchTerm: ''
};

const dataReducer = (state: DataState, action: DataAction): DataState => {
  switch (action.type) {
    case 'FETCH_START':
      return { ...state, loading: true, error: null };
    case 'FETCH_SUCCESS':
      return { 
        ...state, 
        loading: false, 
        data: action.payload,
        filteredData: searchFilter(action.payload, state.searchTerm)
      };
    case 'FETCH_ERROR':
      return { ...state, loading: false, error: action.payload };
    case 'SET_SEARCH':
      return { 
        ...state, 
        searchTerm: action.payload,
        filteredData: searchFilter(state.data, action.payload)
      };
    case 'FILTER_DATA':
      return { ...state, filteredData: action.payload };
    default:
      return state;
  }
};

export const useData = (
  type: DataType,
  eventName: string,
  startDate?: string,
  endDate?: string
) => {
  const [state, dispatch] = useReducer(dataReducer, initialState);

  useEffect(() => {
    if (!eventName) return;

    const loadData = async () => {
      dispatch({ type: 'FETCH_START' });
      
      try {
        let data = await fetchDustData(type, eventName);
        
        // Apply date filtering for schedule and music
        if ((type === 'schedule' || type === 'music') && (startDate || endDate)) {
          data = filterByDateRange(data, startDate, endDate);
        }
        
        // Sort data
        data = sortData(data, type);
        
        dispatch({ type: 'FETCH_SUCCESS', payload: data });
      } catch (error) {
        dispatch({ 
          type: 'FETCH_ERROR', 
          payload: error instanceof Error ? error.message : 'Failed to load data'
        });
      }
    };

    loadData();
  }, [type, eventName, startDate, endDate]);

  const setSearchTerm = (term: string) => {
    dispatch({ type: 'SET_SEARCH', payload: term });
  };

  return {
    ...state,
    setSearchTerm
  };
};