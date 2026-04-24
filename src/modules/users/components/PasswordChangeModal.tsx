import { useState } from 'react';
import { Lock, ShieldCheck } from 'lucide-react';
import type { User } from '../types';
import { Portal } from '../../../components/ui/Portal';

interface PasswordChangeModalProps {
  isOpen: boolean;
  onClose: () => void;
  onConfirm: (password: string) => Promise<void>;
  user: User | null;
}

export const PasswordChangeModal: React.FC<PasswordChangeModalProps> = ({ isOpen, onClose, onConfirm, user }) => {
  const [password, setPassword] = useState('');
  const [confirmPassword, setConfirmPassword] = useState('');
  const [isSubmitting, setIsSubmitting] = useState(false);
  const [error, setError] = useState('');

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    if (password !== confirmPassword) {
      setError('Las contraseñas no coinciden');
      return;
    }
    if (password.length < 8) {
      setError('La contraseña debe tener al menos 8 caracteres');
      return;
    }

    setIsSubmitting(true);
    setError('');
    try {
      await onConfirm(password);
      onClose();
      setPassword('');
      setConfirmPassword('');
    } catch (err) {
      setError('Error al cambiar la contraseña');
    } finally {
      setIsSubmitting(false);
    }
  };

  return (
    <Portal isOpen={isOpen}>
      <div className="fixed inset-0 z-[100] flex items-center justify-center p-4 bg-slate-900/60 animate-fade-in">
        <div className="bg-white w-full max-w-md rounded-[40px] shadow-2xl overflow-hidden flex flex-col animate-scale-in">
          <div className="p-10 space-y-8">
            <div className="flex flex-col items-center text-center space-y-4">
              <div className="h-20 w-20 bg-orange-50 text-[#EE9D4C] rounded-[32px] flex items-center justify-center shadow-inner">
                 <Lock size={32} />
              </div>
              <div>
                <h2 className="text-2xl font-black text-[#004C6C] tracking-tight">Cambiar Contraseña</h2>
                <p className="text-xs font-bold text-slate-400 mt-1 uppercase tracking-widest leading-relaxed">
                  Usuario: {user?.name}
                </p>
              </div>
            </div>

            <form onSubmit={handleSubmit} className="space-y-6">
              <div className="space-y-2">
                 <label className="text-[10px] font-black text-slate-400 uppercase tracking-widest ml-1">Nueva Contraseña</label>
                 <input 
                   required
                   type="password"
                   value={password}
                   onChange={e => setPassword(e.target.value)}
                   className="w-full bg-slate-50 border border-slate-100 rounded-2xl px-6 py-4 text-sm font-bold text-slate-700 focus:bg-white focus:border-[#EE9D4C] outline-none transition-all"
                   placeholder="Mínimo 8 caracteres"
                 />
              </div>
              <div className="space-y-2">
                 <label className="text-[10px] font-black text-slate-400 uppercase tracking-widest ml-1">Confirmar Contraseña</label>
                 <input 
                   required
                   type="password"
                   value={confirmPassword}
                   onChange={e => setConfirmPassword(e.target.value)}
                   className="w-full bg-slate-50 border border-slate-100 rounded-2xl px-6 py-4 text-sm font-bold text-slate-700 focus:bg-white focus:border-[#EE9D4C] outline-none transition-all"
                   placeholder="Repite la contraseña"
                 />
              </div>

              {error && (
                <p className="text-xs font-bold text-red-500 bg-red-50 p-4 rounded-xl text-center border border-red-100">
                  {error}
                </p>
              )}

              <div className="flex flex-col gap-3 pt-4">
                <button 
                  type="submit"
                  disabled={isSubmitting}
                  className="flex items-center justify-center gap-3 w-full py-5 bg-[#004C6C] text-white rounded-2xl font-black text-xs uppercase tracking-widest hover:bg-[#003a53] shadow-lg shadow-blue-900/10 transition-all disabled:opacity-50"
                >
                  {isSubmitting ? 'Procesando...' : (
                    <>
                      <ShieldCheck size={18} />
                      Actualizar Seguridad
                    </>
                  )}
                </button>
                <button 
                  type="button"
                  onClick={onClose}
                  className="w-full py-4 text-slate-400 font-bold text-xs uppercase tracking-widest hover:text-slate-600 transition-colors"
                >
                  Cancelar
                </button>
              </div>
            </form>
          </div>
        </div>
      </div>
    </Portal>
  );
};
