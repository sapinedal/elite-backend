import React, { createContext, useContext, useState, useCallback } from 'react';
import { CheckCircle, AlertCircle, Info, X } from 'lucide-react';

type NotificationType = 'success' | 'error' | 'info' | 'warning';

interface Notification {
  id: number;
  message: string;
  type: NotificationType;
}

interface NotificationContextType {
  showNotification: (message: string, type?: NotificationType) => void;
}

const NotificationContext = createContext<NotificationContextType | undefined>(undefined);

export const useNotification = () => {
  const context = useContext(NotificationContext);
  if (!context) {
    throw new Error('useNotification must be used within a NotificationProvider');
  }
  return context;
};

export const NotificationProvider: React.FC<{ children: React.ReactNode }> = ({ children }) => {
  const [notifications, setNotifications] = useState<Notification[]>([]);

  const showNotification = useCallback((message: string, type: NotificationType = 'info') => {
    const id = Date.now();
    setNotifications((prev) => [...prev, { id, message, type }]);
    setTimeout(() => {
      setNotifications((prev) => prev.filter((n) => n.id !== id));
    }, 4000);
  }, []);

  const removeNotification = (id: number) => {
    setNotifications((prev) => prev.filter((n) => n.id !== id));
  };

  return (
    <NotificationContext.Provider value={{ showNotification }}>
      {children}
      <div className="fixed bottom-8 right-8 z-[200] flex flex-col gap-3">
        {notifications.map((n) => (
          <div
            key={n.id}
            className="flex items-center gap-4 bg-white px-6 py-4 rounded-2xl shadow-2xl border border-slate-100 animate-in slide-in-from-right-10 duration-300 min-w-[320px]"
          >
            <div className={`p-2 rounded-xl ${
              n.type === 'success' ? 'bg-green-50 text-green-500' :
              n.type === 'error' ? 'bg-red-50 text-red-500' :
              n.type === 'warning' ? 'bg-orange-50 text-orange-500' :
              'bg-blue-50 text-blue-500'
            }`}>
              {n.type === 'success' && <CheckCircle size={20} />}
              {n.type === 'error' && <AlertCircle size={20} />}
              {n.type === 'warning' && <AlertCircle size={20} />}
              {n.type === 'info' && <Info size={20} />}
            </div>
            <p className="flex-1 text-sm font-bold text-slate-700">{n.message}</p>
            <button onClick={() => removeNotification(n.id)} className="text-slate-300 hover:text-slate-500">
              <X size={16} />
            </button>
          </div>
        ))}
      </div>
    </NotificationContext.Provider>
  );
};
