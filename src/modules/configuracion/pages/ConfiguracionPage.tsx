import React, { useState, useEffect } from 'react';
import { 
  Building2, 
  Briefcase, 
  Plus, 
  Edit2, 
  Trash2, 
  ChevronRight,
  Info
} from 'lucide-react';
import { configuracionService, type Area } from '../services/configuracionService';

export default function ConfiguracionPage() {
  const [areas, setAreas] = useState<Area[]>([]);
  const [selectedArea, setSelectedArea] = useState<Area | null>(null);
  const [isLoading, setIsLoading] = useState(true);
  
  // Modals state
  const [isAreaModalOpen, setIsAreaModalOpen] = useState(false);
  const [isPositionModalOpen, setIsPositionModalOpen] = useState(false);
  const [editingItem, setEditingItem] = useState<{ type: 'area' | 'position', data: any } | null>(null);

  const fetchAreas = async () => {
    setIsLoading(true);
    try {
      const data = await configuracionService.getAreas();
      setAreas(data);
      if (selectedArea) {
        const updated = data.find(a => a.id === selectedArea.id);
        setSelectedArea(updated || null);
      }
    } catch (error) {
      console.error(error);
    } finally {
      setIsLoading(false);
    }
  };

  useEffect(() => {
    fetchAreas();
  }, []);

  const handleSaveArea = async (e: React.FormEvent) => {
    e.preventDefault();
    const formData = new FormData(e.currentTarget as HTMLFormElement);
    const name = formData.get('name') as string;
    const description = formData.get('description') as string;

    try {
      if (editingItem?.type === 'area') {
        await configuracionService.updateArea(editingItem.data.id, { name, description });
      } else {
        await configuracionService.createArea({ name, description });
      }
      setIsAreaModalOpen(false);
      setEditingItem(null);
      fetchAreas();
    } catch (error) {
      console.error(error);
    }
  };

  const handleSavePosition = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!selectedArea) return;
    const formData = new FormData(e.currentTarget as HTMLFormElement);
    const name = formData.get('name') as string;

    try {
      if (editingItem?.type === 'position') {
        await configuracionService.updatePosition(editingItem.data.id, { name });
      } else {
        await configuracionService.createPosition({ name, area_id: selectedArea.id });
      }
      setIsPositionModalOpen(false);
      setEditingItem(null);
      fetchAreas();
    } catch (error) {
      console.error(error);
    }
  };

  const handleDeleteArea = async (id: number) => {
    if (!window.confirm('¿Estás seguro de eliminar esta área? Se eliminarán todos sus cargos asociados.')) return;
    try {
      await configuracionService.deleteArea(id);
      if (selectedArea?.id === id) setSelectedArea(null);
      fetchAreas();
    } catch (error) {
      console.error(error);
    }
  };

  const handleDeletePosition = async (id: number) => {
    if (!window.confirm('¿Estás seguro de eliminar este cargo?')) return;
    try {
      await configuracionService.deletePosition(id);
      fetchAreas();
    } catch (error) {
      console.error(error);
    }
  };

  return (
    <div className="max-w-7xl mx-auto p-8 space-y-10 animate-fade-in">
      <div className="space-y-1">
        <h1 className="text-4xl font-black text-[#004C6C] tracking-tight">Parametrización Organizacional</h1>
        <p className="text-slate-400 font-bold uppercase tracking-[0.2em]">Define las áreas y cargos de la compañía</p>
      </div>

      <div className="grid grid-cols-1 lg:grid-cols-2 gap-10">
        
        {/* Areas Section */}
        <div className="space-y-6">
          <div className="flex items-center justify-between">
            <h2 className="text-xl font-black text-slate-800 flex items-center gap-3">
              <Building2 className="text-[#004C6C]" size={24} /> Áreas
            </h2>
            <button 
              onClick={() => { setEditingItem(null); setIsAreaModalOpen(true); }}
              className="p-2 bg-blue-50 text-[#004C6C] rounded-xl hover:bg-[#004C6C] hover:text-white transition-all shadow-sm"
            >
              <Plus size={20} />
            </button>
          </div>

          <div className="space-y-4">
            {isLoading ? (
              [1,2,3].map(i => <div key={i} className="h-20 bg-white rounded-3xl animate-pulse border border-slate-100" />)
            ) : areas.map(area => (
              <div 
                key={area.id}
                onClick={() => setSelectedArea(area)}
                className={`
                  p-6 rounded-[32px] border transition-all duration-300 cursor-pointer group flex items-center justify-between
                  ${selectedArea?.id === area.id 
                    ? 'bg-[#004C6C] border-[#004C6C] text-white shadow-xl shadow-blue-900/20' 
                    : 'bg-white border-slate-100 text-slate-600 hover:border-blue-200 hover:shadow-lg'
                  }
                `}
              >
                <div className="flex items-center gap-5">
                  <div className={`h-12 w-12 rounded-2xl flex items-center justify-center transition-colors ${selectedArea?.id === area.id ? 'bg-white/20' : 'bg-slate-50 text-[#004C6C]'}`}>
                    <Building2 size={24} />
                  </div>
                  <div>
                    <p className="font-black tracking-tight text-lg">{area.name}</p>
                    <p className={`text-[10px] font-bold uppercase tracking-widest ${selectedArea?.id === area.id ? 'text-white/60' : 'text-slate-400'}`}>
                      {area.positions?.length || 0} Cargos Definidos
                    </p>
                  </div>
                </div>
                <div className="flex items-center gap-2">
                   <button 
                    onClick={(e) => { e.stopPropagation(); setEditingItem({ type: 'area', data: area }); setIsAreaModalOpen(true); }}
                    className={`p-2 rounded-lg transition-colors ${selectedArea?.id === area.id ? 'hover:bg-white/10 text-white/60 hover:text-white' : 'hover:bg-slate-50 text-slate-300 hover:text-[#EE9D4C]'}`}
                   >
                     <Edit2 size={16} />
                   </button>
                   <button 
                    onClick={(e) => { e.stopPropagation(); handleDeleteArea(area.id); }}
                    className={`p-2 rounded-lg transition-colors ${selectedArea?.id === area.id ? 'hover:bg-white/10 text-white/60 hover:text-white' : 'hover:bg-slate-50 text-slate-300 hover:text-red-500'}`}
                   >
                     <Trash2 size={16} />
                   </button>
                   <ChevronRight size={20} className={`transition-transform duration-300 ${selectedArea?.id === area.id ? 'translate-x-1' : 'opacity-20'}`} />
                </div>
              </div>
            ))}
          </div>
        </div>

        {/* Positions Section */}
        <div className="space-y-6">
          <div className="flex items-center justify-between">
            <h2 className="text-xl font-black text-slate-800 flex items-center gap-3">
              <Briefcase className="text-[#EE9D4C]" size={24} /> Cargos
            </h2>
            {selectedArea && (
              <button 
                onClick={() => { setEditingItem(null); setIsPositionModalOpen(true); }}
                className="p-2 bg-orange-50 text-[#EE9D4C] rounded-xl hover:bg-[#EE9D4C] hover:text-white transition-all shadow-sm"
              >
                <Plus size={20} />
              </button>
            )}
          </div>

          {!selectedArea ? (
            <div className="bg-slate-50 rounded-[40px] border-2 border-dashed border-slate-200 p-20 text-center flex flex-col items-center gap-4">
               <div className="h-16 w-16 bg-white rounded-3xl shadow-sm flex items-center justify-center text-slate-300">
                  <Building2 size={32} />
               </div>
               <p className="text-slate-400 font-bold max-w-[200px]">Selecciona un área para ver y gestionar sus cargos</p>
            </div>
          ) : (
            <div className="space-y-4">
              <div className="bg-blue-50/50 p-6 rounded-[32px] border border-blue-100 flex items-center gap-4 mb-8">
                 <Info size={20} className="text-[#004C6C]" />
                 <p className="text-xs font-bold text-[#004C6C] leading-relaxed uppercase tracking-widest">
                    Gestionando cargos para: <span className="font-black underline">{selectedArea.name}</span>
                 </p>
              </div>

              {selectedArea.positions?.map(pos => (
                <div 
                  key={pos.id}
                  className="bg-white p-6 rounded-[32px] border border-slate-100 hover:border-orange-200 transition-all group flex items-center justify-between shadow-sm hover:shadow-md"
                >
                  <div className="flex items-center gap-4">
                    <div className="h-10 w-10 rounded-xl bg-orange-50 text-[#EE9D4C] flex items-center justify-center group-hover:bg-[#EE9D4C] group-hover:text-white transition-all">
                      <Briefcase size={20} />
                    </div>
                    <span className="font-black text-slate-700 tracking-tight">{pos.name}</span>
                  </div>
                  <div className="flex items-center gap-1">
                    <button 
                      onClick={() => { setEditingItem({ type: 'position', data: pos }); setIsPositionModalOpen(true); }}
                      className="p-2 text-slate-300 hover:text-[#EE9D4C] hover:bg-orange-50 rounded-xl transition-all"
                    >
                      <Edit2 size={16} />
                    </button>
                    <button 
                      onClick={() => handleDeletePosition(pos.id)}
                      className="p-2 text-slate-300 hover:text-red-500 hover:bg-red-50 rounded-xl transition-all"
                    >
                      <Trash2 size={16} />
                    </button>
                  </div>
                </div>
              ))}
              
              {selectedArea.positions?.length === 0 && (
                <div className="text-center py-10">
                   <p className="text-slate-400 font-bold italic">No hay cargos registrados para esta área.</p>
                </div>
              )}
            </div>
          )}
        </div>
      </div>

      {/* Modals */}
      {(isAreaModalOpen || isPositionModalOpen) && (
        <div className="fixed inset-0 z-[100] flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-sm animate-fade-in">
          <div className="bg-white w-full max-w-md rounded-[40px] shadow-2xl overflow-hidden animate-scale-in">
            <div className="p-10 space-y-8">
              <div className="flex flex-col items-center text-center space-y-4">
                <div className={`h-20 w-20 rounded-[32px] flex items-center justify-center shadow-inner ${isAreaModalOpen ? 'bg-blue-50 text-[#004C6C]' : 'bg-orange-50 text-[#EE9D4C]'}`}>
                   {isAreaModalOpen ? <Building2 size={32} /> : <Briefcase size={32} />}
                </div>
                <div>
                  <h2 className="text-2xl font-black text-[#004C6C] tracking-tight">
                    {editingItem ? 'Editar' : 'Nuevo'} {isAreaModalOpen ? 'Área' : 'Cargo'}
                  </h2>
                  <p className="text-[10px] font-bold text-slate-400 uppercase tracking-widest mt-1">
                    {isAreaModalOpen ? 'Define una división organizacional' : `Añade un cargo a ${selectedArea?.name}`}
                  </p>
                </div>
              </div>

              <form onSubmit={isAreaModalOpen ? handleSaveArea : handleSavePosition} className="space-y-6">
                <div className="space-y-2">
                   <label className="text-[10px] font-black text-slate-400 uppercase tracking-widest ml-1">Nombre</label>
                   <input 
                     required
                     name="name"
                     defaultValue={editingItem?.data?.name || ''}
                     className="w-full bg-slate-50 border border-slate-100 rounded-2xl px-6 py-4 text-sm font-bold text-slate-700 focus:bg-white focus:border-[#004C6C] outline-none transition-all"
                     placeholder={isAreaModalOpen ? "Ej: Comercial" : "Ej: Analista Senior"}
                   />
                </div>
                
                {isAreaModalOpen && (
                  <div className="space-y-2">
                     <label className="text-[10px] font-black text-slate-400 uppercase tracking-widest ml-1">Descripción</label>
                     <textarea 
                       name="description"
                       defaultValue={editingItem?.data?.description || ''}
                       className="w-full bg-slate-50 border border-slate-100 rounded-2xl px-6 py-4 text-sm font-bold text-slate-700 focus:bg-white focus:border-[#004C6C] outline-none transition-all min-h-[100px]"
                       placeholder="Opcional..."
                     />
                  </div>
                )}

                <div className="flex flex-col gap-3 pt-4">
                  <button 
                    type="submit"
                    className={`flex items-center justify-center gap-3 w-full py-5 text-white rounded-2xl font-black text-xs uppercase tracking-widest shadow-lg transition-all ${isAreaModalOpen ? 'bg-[#004C6C] hover:bg-[#003a53]' : 'bg-[#EE9D4C] hover:bg-[#d68a3d]'}`}
                  >
                    <Plus size={18} />
                    {editingItem ? 'Guardar Cambios' : `Crear ${isAreaModalOpen ? 'Área' : 'Cargo'}`}
                  </button>
                  <button 
                    type="button"
                    onClick={() => { setIsAreaModalOpen(false); setIsPositionModalOpen(false); setEditingItem(null); }}
                    className="w-full py-4 text-slate-400 font-bold text-xs uppercase tracking-widest hover:text-slate-600 transition-colors"
                  >
                    Cancelar
                  </button>
                </div>
              </form>
            </div>
          </div>
        </div>
      )}
    </div>
  );
}
