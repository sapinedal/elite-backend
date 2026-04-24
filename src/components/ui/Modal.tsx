import React from 'react';
import { X, AlertCircle, HelpCircle } from 'lucide-react';
import { Portal } from './Portal';

interface ModalProps {
  isOpen: boolean;
  onClose: () => void;
  onConfirm?: () => void;
  title: string;
  message: string;
  confirmText?: string;
  cancelText?: string;
  type?: 'info' | 'warning' | 'danger' | 'success';
}

export const Modal: React.FC<ModalProps> = ({
  isOpen,
  onClose,
  onConfirm,
  title,
  message,
  confirmText = 'Confirmar',
  cancelText = 'Cancelar',
  type = 'info'
}) => {
  const getTypeStyles = () => {
    switch (type) {
      case 'danger': return { icon: <AlertCircle className="text-red-500" size={24} />, button: 'bg-red-500 hover:bg-red-600 shadow-red-200' };
      case 'warning': return { icon: <AlertCircle className="text-orange-500" size={24} />, button: 'bg-orange-500 hover:bg-orange-600 shadow-orange-200' };
      case 'success': return { icon: <HelpCircle className="text-green-500" size={24} />, button: 'bg-green-500 hover:bg-green-600 shadow-green-200' };
      default: return { icon: <HelpCircle className="text-[#004C6C]" size={24} />, button: 'bg-[#004C6C] hover:bg-[#003a53] shadow-blue-200' };
    }
  };

  const styles = getTypeStyles();

  return (
    <Portal isOpen={isOpen}>
      <div className="fixed inset-0 z-[9999] flex items-center justify-center p-4 bg-slate-900/40 animate-in fade-in duration-200">
        <div className="bg-white w-full max-w-md rounded-[32px] shadow-2xl overflow-hidden animate-in zoom-in-95 duration-200">
          <div className="p-8">
            <div className="flex justify-between items-start mb-6">
              <div className="p-3 bg-slate-50 rounded-2xl">
                {styles.icon}
              </div>
              <button onClick={onClose} className="p-2 text-slate-400 hover:bg-slate-50 rounded-xl transition-colors">
                <X size={20} />
              </button>
            </div>

            <h3 className="text-xl font-extrabold text-slate-800 mb-2 tracking-tight">{title}</h3>
            <p className="text-slate-500 font-medium leading-relaxed">{message}</p>

            <div className="flex gap-4 mt-10">
              <button
                onClick={onClose}
                className="flex-1 px-6 py-4 bg-slate-50 text-slate-500 rounded-2xl font-bold hover:bg-slate-100 transition-all tracking-tight"
              >
                {cancelText}
              </button>
              {onConfirm && (
                <button
                  onClick={() => {
                    onConfirm();
                    onClose();
                  }}
                  className={`flex-1 px-6 py-4 text-white rounded-2xl font-bold shadow-xl transition-all hover:scale-[1.02] active:scale-[0.98] tracking-tight ${styles.button}`}
                >
                  {confirmText}
                </button>
              )}
            </div>
          </div>
        </div>
      </div>
    </Portal>
  );
};
