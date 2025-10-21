import React from 'react';
import { DustItem as DustItemType, DataType } from '../types';
import { getImageUrl, parsePinCoordinates } from '../utils/api';
import styles from '../styles/components.module.css';

interface Props {
  item: DustItemType;
  type: DataType;
  showImages: boolean;
  showCoordinates: boolean;
  layout: 'grid' | 'list';
  onClick?: (item: DustItemType) => void;
}

const formatDescription = (description?: string): string => {
  if (!description) return '';
  return description.split('\n').map(p => p.trim()).filter(p => p).join('</p><p>');
};

const formatCategories = (item: DustItemType, type: DataType): string => {
  if (type === 'schedule' && item.event_type?.label) return item.event_type.label;
  if (type === 'camps' && item.camp_type) return item.camp_type;
  if (type === 'art' && item.art_type) return item.art_type;
  return '';
};

export const DustItem: React.FC<Props> = ({ 
  item, 
  type, 
  showImages, 
  showCoordinates, 
  layout,
  onClick 
}) => {
  const handleClick = () => {
    onClick?.(item);
  };

  const handleKeyDown = (e: React.KeyboardEvent) => {
    if (e.key === 'Enter' || e.key === ' ') {
      e.preventDefault();
      onClick?.(item);
    }
  };

  const renderImage = () => {
    if (!showImages) return null;

    let imageUrl: string | null = null;
    
    if (type === 'art' && item.images?.[0]?.thumbnail_url) {
      imageUrl = item.images[0].thumbnail_url;
    } else if (item.imageUrl) {
      imageUrl = getImageUrl(item.imageUrl);
    }

    return (
      <div className={`${styles.image} ${layout === 'list' ? styles.listImage : ''}`}>
        {imageUrl ? (
          <img 
            src={imageUrl} 
            alt="" 
            role="presentation" 
            loading="lazy"
            onError={(e) => {
              const target = e.target as HTMLImageElement;
              target.style.display = 'none';
              const placeholder = target.parentElement?.querySelector('.placeholder');
              if (placeholder) {
                (placeholder as HTMLElement).style.display = 'flex';
              }
            }}
          />
        ) : null}
        <div className={styles.imagePlaceholder} style={{ display: imageUrl ? 'none' : 'flex' }}>
          <svg width="60" height="60" viewBox="0 0 24 24" fill="#ccc">
            <path d="M21,19V5C21,3.89 20.1,3 19,3H5A2,2 0 0,0 3,5V19A2,2 0 0,0 5,21H19A2,2 0 0,0 21,19M19,19H5V5H19V19Z"/>
          </svg>
          <p>No Image</p>
        </div>
      </div>
    );
  };

  const renderContent = () => {
    const name = item.name || item.title || '';
    const description = formatDescription(item.description);
    const categories = formatCategories(item, type);
    const pinData = parsePinCoordinates(item.pin);

    return (
      <div className={`${styles.content} ${layout === 'list' ? styles.listContent : ''}`}>
        <h3 className={styles.title}>{name}</h3>
        
        {type === 'art' && item.artist && (
          <div className={styles.field}>
            <strong>Artist:</strong> {item.artist}
          </div>
        )}
        
        {(item.camp || item.hosted_by_camp) && (
          <div className={styles.field}>
            <strong>Camp:</strong> {item.camp || item.hosted_by_camp}
          </div>
        )}
        
        {item.location && (
          <div className={styles.field}>
            <strong>Location:</strong> {item.location}
          </div>
        )}
        
        {categories && (
          <div className={styles.field}>
            <strong>Categories:</strong> {categories}
          </div>
        )}
        
        {item.day && (
          <div className={styles.field}>
            <strong>Day:</strong> {item.day}
          </div>
        )}
        
        {item.occurrence?.who && (
          <div className={styles.field}>
            <strong>Artist:</strong> {item.occurrence.who}
          </div>
        )}
        
        {item.occurrence?.long && (
          <div className={styles.field}>
            <strong>Time:</strong> {item.occurrence.long}
          </div>
        )}
        
        {description && (
          <div 
            className={styles.description}
            dangerouslySetInnerHTML={{ __html: `<p>${description}</p>` }}
          />
        )}
        
        {showCoordinates && pinData && (
          <div className={styles.coordinates}>
            <strong>Coordinates:</strong>{' '}
            {pinData.lat && pinData.lng ? (
              `GPS - Lat: ${pinData.lat}, Lng: ${pinData.lng}`
            ) : pinData.x && pinData.y ? (
              `Map Position - X: ${pinData.x}, Y: ${pinData.y}`
            ) : null}
          </div>
        )}
      </div>
    );
  };

  return (
    <article 
      className={`${styles.item} ${layout === 'list' ? styles.listItem : ''}`}
      data-uid={item.uid}
      role="article"
      tabIndex={0}
      onClick={handleClick}
      onKeyDown={handleKeyDown}
    >
      {renderImage()}
      {renderContent()}
    </article>
  );
};