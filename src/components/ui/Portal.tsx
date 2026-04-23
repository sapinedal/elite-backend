import React, { useEffect } from 'react';
import { createPortal } from 'react-dom';

interface PortalProps {
  children: React.ReactNode;
  isOpen: boolean;
}

export const Portal: React.FC<PortalProps> = ({ children, isOpen }) => {
  useEffect(() => {
    const layout = document.getElementById('root-layout');
    if (!layout) return;

    if (isOpen) {
      layout.classList.add('global-blur');
    } else {
      layout.classList.remove('global-blur');
    }

    return () => {
      layout.classList.remove('global-blur');
    };
  }, [isOpen]);

  if (!isOpen) return null;

  return createPortal(children, document.body);
};
