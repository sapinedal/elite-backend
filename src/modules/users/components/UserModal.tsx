import { useState, useEffect } from 'react';
import { X, Save, User as UserIcon, Mail, FileText, Lock } from 'lucide-react';
import type { User } from '../types';
import { Portal } from '../../../components/ui/Portal';
import { CustomSelect } from '../../../components/ui/CustomSelect';
import { configuracionService, type Area, type Position } from '../../configuracion/services/configuracionService';

interface UserModalProps {
  isOpen: boolean;
  onClose: () => void;
  onSave: (data: any) => Promise<void>;
  user: User | null;
}

export const UserModal: React.FC<UserModalProps> = ({ isOpen, onClose, onSave, user }) => {
  const [formData, setFormData] = useState({
    first_name: '',
    last_name: '',
    email: '',
    password: '',
    area_id: '',
    position_id: '',
    document: '',
    roles: ['colaborador']
  });
  
  const [areas, setAreas] = useState<Area[]>([]);
  const [availablePositions, setAvailablePositions] = useState<Position[]>([]);
  const [isSubmitting, setIsSubmitting] = useState(false);

  useEffect(() => {
    const fetchData = async () => {
      try {
        const areasData = await configuracionService.getAreas();
        setAreas(areasData);
      } catch (error) {
        console.error(error);
      }
    };
    fetchData();
  }, []);

  useEffect(() => {
    if (user) {
      setFormData({
        first_name: user.name.split(' ')[0] || '',
        last_name: user.name.split(' ').slice(1).join(' ') || '',
        email: user.email,
        password: '', // Password shouldn't be loaded
        area_id: user.area_id?.toString() || '',
        position_id: user.position_id?.toString() || '',
        document: user.document || '',
        roles: user.roles || ['colaborador']
      });

      // Load positions for the user's area
      const selectedArea = areas.find(a => a.id === user.area_id);
      if (selectedArea) {
        setAvailablePositions(selectedArea.positions);
      }
    } else {
      setFormData({
        first_name: '',
        last_name: '',
        email: '',
        password: '',
        area_id: '',
        position_id: '',
        document: '',
        roles: ['colaborador']
      });
      setAvailablePositions([]);
    }
  }, [user, isOpen, areas]);

  const handleAreaChange = (areaId: string) => {
    setFormData({ ...formData, area_id: areaId, position_id: '' });
    const selectedArea = areas.find(a => a.id.toString() === areaId);
    if (selectedArea) {
      setAvailablePositions(selectedArea.positions);
    } else {
      setAvailablePositions([]);
    }
  };

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    setIsSubmitting(true);
    try {
      await onSave(formData);
      onClose();
    } catch (error) {
      console.error(error);
    } finally {
      setIsSubmitting(false);
    }
  };

  return (
    <Portal isOpen={isOpen}>
      <div className="fixed inset-0 z-[100] flex items-center justify-center p-4 bg-slate-900/60 animate-fade-in">
        <div className="bg-[#f8fafc] w-full max-w-2xl rounded-[40px] shadow-2xl overflow-hidden flex flex-col animate-scale-in">
          
          {/* Header */}
          <div className="bg-white p-8 border-b border-slate-100 flex items-center justify-between">
            <div className="flex items-center gap-4">
              <div className="h-12 w-12 bg-blue-50 text-[#004C6C] rounded-2xl flex items-center justify-center">
                <UserIcon size={24} />
              </div>
              <div>
                <h2 className="text-2xl font-black text-[#004C6C] tracking-tight">
                  {user ? 'Editar Usuario' : 'Nuevo Usuario'}
                </h2>
                <p className="text-[10px] font-bold text-slate-400 uppercase tracking-widest mt-0.5">
                  Completa los datos del colaborador
                </p>
              </div>
            </div>
            <button onClick={onClose} className="p-3 text-slate-300 hover:bg-slate-50 rounded-2xl transition-all">
              <X size={24} />
            </button>
          </div>

          {/* Form */}
          <form onSubmit={handleSubmit} className="p-10 space-y-8 overflow-y-auto max-h-[70vh]">
            <div className="grid grid-cols-1 md:grid-cols-2 gap-8">
              {/* First Name */}
              <div className="space-y-2">
                <label className="text-[10px] font-black text-slate-400 uppercase tracking-widest ml-1">Nombres</label>
                <div className="relative">
                  <div className="absolute left-4 top-1/2 -translate-y-1/2 text-slate-300 z-10"><UserIcon size={18} /></div>
                  <input 
                    required
                    type="text"
                    value={formData.first_name}
                    onChange={e => setFormData({...formData, first_name: e.target.value})}
                    className="w-full bg-white border border-slate-100 rounded-2xl pl-12 pr-6 py-4 text-sm font-bold text-slate-700 focus:border-[#004C6C] outline-none transition-all shadow-sm"
                    placeholder="Ej: Juan Camilo"
                  />
                </div>
              </div>

              {/* Last Name */}
              <div className="space-y-2">
                <label className="text-[10px] font-black text-slate-400 uppercase tracking-widest ml-1">Apellidos</label>
                <div className="relative">
                  <div className="absolute left-4 top-1/2 -translate-y-1/2 text-slate-300 z-10"><UserIcon size={18} /></div>
                  <input 
                    required
                    type="text"
                    value={formData.last_name}
                    onChange={e => setFormData({...formData, last_name: e.target.value})}
                    className="w-full bg-white border border-slate-100 rounded-2xl pl-12 pr-6 py-4 text-sm font-bold text-slate-700 focus:border-[#004C6C] outline-none transition-all shadow-sm"
                    placeholder="Ej: Pérez García"
                  />
                </div>
              </div>

              {/* Email */}
              <div className="space-y-2">
                <label className="text-[10px] font-black text-slate-400 uppercase tracking-widest ml-1">Email</label>
                <div className="relative">
                  <div className="absolute left-4 top-1/2 -translate-y-1/2 text-slate-300 z-10"><Mail size={18} /></div>
                  <input 
                    required
                    type="email"
                    value={formData.email}
                    onChange={e => setFormData({...formData, email: e.target.value})}
                    className="w-full bg-white border border-slate-100 rounded-2xl pl-12 pr-6 py-4 text-sm font-bold text-slate-700 focus:border-[#004C6C] outline-none transition-all shadow-sm"
                    placeholder="juan.perez@empresa.com"
                  />
                </div>
              </div>

              {/* Document */}
              <div className="space-y-2">
                <label className="text-[10px] font-black text-slate-400 uppercase tracking-widest ml-1">Documento / ID</label>
                <div className="relative">
                  <div className="absolute left-4 top-1/2 -translate-y-1/2 text-slate-300 z-10"><FileText size={18} /></div>
                  <input 
                    required
                    type="text"
                    value={formData.document}
                    onChange={e => setFormData({...formData, document: e.target.value})}
                    className="w-full bg-white border border-slate-100 rounded-2xl pl-12 pr-6 py-4 text-sm font-bold text-slate-700 focus:border-[#004C6C] outline-none transition-all shadow-sm"
                    placeholder="CC 12345678"
                  />
                </div>
              </div>

              {/* Area */}
              <div className="space-y-2">
                <CustomSelect 
                  label="Área"
                  placeholder="Selecciona el área"
                  options={areas.map(a => ({ value: a.id.toString(), label: a.name }))}
                  value={formData.area_id}
                  onChange={handleAreaChange}
                />
              </div>

              {/* Position */}
              <div className="space-y-2">
                <CustomSelect 
                  label="Cargo"
                  placeholder={formData.area_id ? "Selecciona el cargo" : "Primero elige un área"}
                  options={availablePositions.map(p => ({ value: p.id.toString(), label: p.name }))}
                  value={formData.position_id}
                  onChange={posId => setFormData({ ...formData, position_id: posId })}
                />
              </div>

              {/* Password (only for new users) */}
              {!user && (
                <div className="space-y-2 md:col-span-2">
                  <label className="text-[10px] font-black text-slate-400 uppercase tracking-widest ml-1">Password Temporal</label>
                  <div className="relative">
                    <div className="absolute left-4 top-1/2 -translate-y-1/2 text-slate-300 z-10"><Lock size={18} /></div>
                    <input 
                      required
                      type="password"
                      value={formData.password}
                      onChange={e => setFormData({...formData, password: e.target.value})}
                      className="w-full bg-white border border-slate-100 rounded-2xl pl-12 pr-6 py-4 text-sm font-bold text-slate-700 focus:border-[#004C6C] outline-none transition-all shadow-sm"
                      placeholder="********"
                    />
                  </div>
                </div>
              )}
            </div>
          </form>

          {/* Footer */}
          <div className="p-8 bg-white border-t border-slate-100 flex justify-end gap-4">
            <button 
              type="button"
              onClick={onClose}
              className="px-8 py-4 bg-slate-100 text-slate-500 rounded-2xl font-bold hover:bg-slate-200 transition-all text-xs uppercase tracking-widest"
            >
              Cancelar
            </button>
            <button 
              onClick={handleSubmit}
              disabled={isSubmitting}
              className="flex items-center gap-3 px-10 py-4 bg-[#004C6C] text-white rounded-2xl font-black text-xs uppercase tracking-widest hover:bg-[#003a53] shadow-lg shadow-blue-900/10 transition-all disabled:opacity-50"
            >
              {isSubmitting ? 'Guardando...' : (
                <>
                  <Save size={18} />
                  {user ? 'Actualizar Usuario' : 'Crear Usuario'}
                </>
              )}
            </button>
          </div>
        </div>
      </div>
    </Portal>
  );
};
