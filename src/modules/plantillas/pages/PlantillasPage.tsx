import { useState, useEffect } from 'react';
import { useUsers, useUserKPIs } from '../../users/hooks/useUsers';
import type { User, KPI, Indicator, IndicatorParameter } from '../../users/types';
import { Plus, Trash2, Save, Users, Target, ShieldAlert, ArrowLeft, List, Settings2, Info, Calculator } from 'lucide-react';
import { useNotification } from '../../../context/NotificationContext';
import { Skeleton } from '../../../components/ui/Skeleton';
import { Modal } from '../../../components/ui/Modal';

export default function PlantillasPage() {
  const { users, isLoading: usersLoading } = useUsers();
  const { showNotification } = useNotification();
  const [selectedUser, setSelectedUser] = useState<User | null>(null);
  const { kpis, isLoading: kpisLoading, saveKPIs } = useUserKPIs(selectedUser?.id || null);
  const [localKpis, setLocalKpis] = useState<Partial<KPI>[]>([]);
  const [isSaving, setIsSaving] = useState(false);
  const [isRemoveModalOpen, setIsRemoveModalOpen] = useState<{ open: boolean, index: number | null }>({ open: false, index: null });
  const [isSidebarCollapsed, setIsSidebarCollapsed] = useState(false);

  // UX State
  const [activeView, setActiveView] = useState<'list' | 'kpi-edit' | 'indicator-edit'>('list');
  const [activeKpiIdx, setActiveKpiIdx] = useState<number | null>(null);
  const [activeIndIdx, setActiveIndIdx] = useState<number | null>(null);

  // Update local KPIs when kpis from server change
  useEffect(() => {
    if (kpis && selectedUser) {
      setLocalKpis(kpis);
      setActiveView('list');
      setActiveKpiIdx(null);
      setActiveIndIdx(null);
    }
  }, [kpis, selectedUser]);

  const handleAddKPI = () => {
    const newKpi: Partial<KPI> = {
      name: '',
      description: '',
      target: 0,
      unit: '%',
      weight: 0,
      lower_is_better: false,
      indicators: []
    };
    setLocalKpis([...localKpis, newKpi]);
    showNotification('Nuevo KPI agregado a la lista', 'info');
  };

  const handleRemoveKPI = () => {
    if (isRemoveModalOpen.index === null) return;
    const updated = [...localKpis];
    updated.splice(isRemoveModalOpen.index, 1);
    setLocalKpis(updated);
    setIsRemoveModalOpen({ open: false, index: null });
    showNotification('KPI eliminado', 'warning');
  };

  const handleUpdateKPI = (index: number, fields: Partial<KPI>) => {
    const updated = [...localKpis];
    updated[index] = { ...updated[index], ...fields };
    setLocalKpis(updated);
  };

  const handleAddIndicator = (kpiIndex: number) => {
    const updated = [...localKpis];
    const kpi = updated[kpiIndex];
    const newIndicator: Indicator = {
      name: '',
      definition: '',
      formula: '',
      fixed_goal: 0,
      conditional_goals: [
        { level: 'Excelente', min_value: 100, max_value: 999, qualification: 'Meta alcanzada o superada', color: 'excellent', score: 100 },
        { level: 'Aceptable', min_value: 80, max_value: 99, qualification: 'Buen desempeño, requiere seguimiento', color: 'acceptable', score: 90 },
        { level: 'En riesgo', min_value: 60, max_value: 79, qualification: 'Bajo cumplimiento, tendencia descendente', color: 'at_risk', score: 70 },
        { level: 'Deficiente', min_value: 0, max_value: 59, qualification: 'Incumplimiento crítico', color: 'deficient', score: 0 }
      ],
      parameters: []
    };
    kpi.indicators = [...(kpi.indicators || []), newIndicator];
    setLocalKpis(updated);
    showNotification('Indicador agregado al KPI', 'info');
  };

  const handleUpdateIndicator = (kpiIndex: number, indicatorIndex: number, fields: Partial<Indicator>) => {
    const updated = [...localKpis];
    const kpi = updated[kpiIndex];
    if (kpi.indicators) {
      kpi.indicators[indicatorIndex] = { ...kpi.indicators[indicatorIndex], ...fields };
      setLocalKpis(updated);
    }
  };

  const handleRemoveIndicator = (kpiIndex: number, indicatorIndex: number) => {
    const updated = [...localKpis];
    const kpi = updated[kpiIndex];
    if (kpi.indicators) {
      kpi.indicators.splice(indicatorIndex, 1);
      setLocalKpis(updated);
      showNotification('Indicador eliminado', 'warning');
    }
  };

  const handleAddParameter = (kpiIndex: number, indicatorIndex: number) => {
    const updated = [...localKpis];
    const kpi = updated[kpiIndex];
    if (kpi.indicators) {
      const indicator = kpi.indicators[indicatorIndex];
      indicator.parameters = [...(indicator.parameters || []), { name: '', value: 0 }];
      setLocalKpis(updated);
    }
  };

  const handleUpdateParameter = (kpiIndex: number, indicatorIndex: number, paramIndex: number, fields: Partial<IndicatorParameter>) => {
    const updated = [...localKpis];
    const kpi = updated[kpiIndex];
    if (kpi.indicators) {
      const indicator = kpi.indicators[indicatorIndex];
      indicator.parameters[paramIndex] = { ...indicator.parameters[paramIndex], ...fields };
      setLocalKpis(updated);
    }
  };

  const handleRemoveParameter = (kpiIndex: number, indicatorIndex: number, paramIndex: number) => {
    const updated = [...localKpis];
    const kpi = updated[kpiIndex];
    if (kpi.indicators) {
      const indicator = kpi.indicators[indicatorIndex];
      indicator.parameters.splice(paramIndex, 1);
      setLocalKpis(updated);
    }
  };

  const handleAddGoal = (kpiIndex: number, indicatorIndex: number) => {
    const updated = [...localKpis];
    const kpi = updated[kpiIndex];
    if (kpi.indicators) {
      const indicator = kpi.indicators[indicatorIndex];
      indicator.conditional_goals = [
        ...(indicator.conditional_goals || []),
        { level: 'Nuevo Nivel', min_value: 0, max_value: 0, qualification: '', color: 'acceptable', score: 0 }
      ];
      setLocalKpis(updated);
    }
  };

  const handleRemoveGoal = (kpiIndex: number, indicatorIndex: number, goalIndex: number) => {
    const updated = [...localKpis];
    const kpi = updated[kpiIndex];
    if (kpi.indicators) {
      const indicator = kpi.indicators[indicatorIndex];
      indicator.conditional_goals.splice(goalIndex, 1);
      setLocalKpis(updated);
    }
  };

  const handleSave = async () => {
    if (!selectedUser) return;

    // Validar suma de pesos
    const totalWeight = localKpis.reduce((acc, curr) => acc + Number(curr.weight || 0), 0);
    if (totalWeight !== 100) {
      showNotification(`La suma de pesos debe ser 100%. Actualmente es ${totalWeight}%`, 'error');
      return;
    }

    setIsSaving(true);
    try {
      await saveKPIs(localKpis);
      showNotification('Plantilla de KPIs guardada con éxito', 'success');
    } catch (error) {
      showNotification('Error al intentar guardar la plantilla', 'error');
    } finally {
      setIsSaving(false);
    }
  };

  const groupedUsers = users.reduce((acc, user) => {
    const areaName = typeof user.area === 'object' ? user.area?.name : user.area;
    const key = areaName || 'Sin Área';
    if (!acc[key]) acc[key] = [];
    acc[key].push(user);
    return acc;
  }, {} as Record<string, User[]>);

  const totalWeight = localKpis.reduce((acc, curr) => acc + Number(curr.weight || 0), 0);

  return (
    <div className="max-w-[1600px] mx-auto flex flex-col lg:flex-row min-h-[calc(100vh-140px)] gap-6 md:gap-8 py-4 md:py-8 px-4">
      <style>{`
        @keyframes fadeIn {
          from { opacity: 0; transform: translateY(10px); }
          to { opacity: 1; transform: translateY(0); }
        }
        @keyframes slideInRight {
          from { opacity: 0; transform: translateX(20px); }
          to { opacity: 1; transform: translateX(0); }
        }
        .animate-fade-in {
          animation: fadeIn 0.4s ease-out forwards;
        }
        .animate-slide-in {
          animation: slideInRight 0.4s ease-out forwards;
        }
      `}</style>

      {/* Columna Izquierda: Directorio de Usuarios */}
      <div className={`transition-all duration-500 ease-in-out flex flex-col bg-white rounded-[40px] shadow-[0_8px_40px_rgb(0,0,0,0.03)] border border-slate-100 overflow-hidden shrink-0 relative ${isSidebarCollapsed ? 'w-0 lg:w-0 opacity-0 -ml-8 overflow-hidden' : 'w-full lg:w-96'
        }`}>
        {isSidebarCollapsed && (
          <button
            onClick={() => setIsSidebarCollapsed(false)}
            className="absolute right-[-40px] top-10 z-50 h-10 w-10 bg-[#004C6C] text-white rounded-r-xl flex items-center justify-center shadow-lg"
          >
            <Users size={20} />
          </button>
        )}
        <div className={`p-8 border-b border-slate-50 bg-white sticky top-0 z-10 space-y-4 transition-all duration-500 ${isSidebarCollapsed ? 'opacity-0 invisible' : 'opacity-100 visible'}`}>
          <div className="flex items-center justify-between">
            <div className="flex items-center gap-4">
              <div className="p-3 bg-slate-50 rounded-2xl text-[#004C6C]">
                <Users size={24} />
              </div>
              <div>
                <h2 className="text-xl font-extrabold text-[#004C6C] tracking-tight">Directorio</h2>
                <p className="text-[10px] uppercase font-black text-slate-300 tracking-[0.2em]">Configuración de KPIs</p>
              </div>
            </div>
            {selectedUser && (
              <button
                onClick={() => setIsSidebarCollapsed(true)}
                className="p-2 hover:bg-slate-50 rounded-xl text-slate-300 transition-colors"
              >
                <ArrowLeft size={20} />
              </button>
            )}
          </div>

          <div className="relative">
            <input
              type="text"
              placeholder="Buscar colaborador..."
              className="w-full bg-slate-50 border-none rounded-2xl px-5 py-3 text-sm font-semibold text-slate-700 outline-none placeholder:text-slate-300 focus:ring-4 focus:ring-blue-50 transition-all"
            />
          </div>
        </div>

        <div className="flex-1 overflow-y-auto p-4 space-y-6 custom-scrollbar">
          {usersLoading ? (
            <div className="space-y-4 p-4">
              {[1, 2, 3, 4, 5].map(i => <Skeleton key={i} className="h-14 w-full rounded-2xl" />)}
            </div>
          ) : (
            Object.entries(groupedUsers).map(([area, areaUsers]) => (
              <div key={area} className="space-y-2">
                <h3 className="text-[10px] font-black text-slate-300 uppercase tracking-[0.3em] px-4 py-2 flex items-center justify-between">
                  <span>{area}</span>
                  <span className="h-4 w-4 bg-slate-50 rounded flex items-center justify-center text-[8px]">{areaUsers.length}</span>
                </h3>
                <div className="space-y-1">
                  {areaUsers.map(user => (
                    <button
                      key={user.id}
                      onClick={() => {
                        setSelectedUser(user);
                        setIsSidebarCollapsed(true);
                      }}
                      className={`w-full group flex items-center gap-4 p-4 rounded-3xl transition-all duration-300 ${selectedUser?.id === user.id
                        ? 'bg-[#004C6C] text-white shadow-xl shadow-blue-900/20 translate-x-2'
                        : 'text-slate-600 hover:bg-slate-50 border border-transparent hover:border-slate-100'
                        }`}
                    >
                      <div className={`h-10 w-10 rounded-2xl flex items-center justify-center font-black text-xs ${selectedUser?.id === user.id ? 'bg-white/10' : 'bg-slate-100/50'}`}>
                        {user.name.charAt(0)}
                      </div>
                      <div className="text-left flex-1 overflow-hidden">
                        <p className={`font-extrabold truncate tracking-tight ${selectedUser?.id === user.id ? 'text-white' : 'text-slate-700'}`}>{user.name}</p>
                        <p className={`text-[10px] font-bold uppercase tracking-widest truncate ${selectedUser?.id === user.id ? 'text-white/60' : 'text-slate-400'}`}>
                          {typeof user.position === 'object' ? user.position?.name : user.position}
                        </p>
                      </div>
                      {selectedUser?.id === user.id && <div className="h-2 w-2 bg-[#EE9D4C] rounded-full"></div>}
                    </button>
                  ))}
                </div>
              </div>
            ))
          )}
        </div>
      </div>

      {/* Botón para re-abrir directorio cuando está colapsado */}
      {isSidebarCollapsed && (
        <button
          onClick={() => setIsSidebarCollapsed(false)}
          className="fixed left-0 top-1/2 -translate-y-1/2 z-50 h-32 w-10 bg-[#004C6C] text-white rounded-r-3xl flex items-center justify-center shadow-[4px_0_24px_rgba(0,76,108,0.3)] hover:w-12 transition-all group overflow-hidden border-y border-r border-blue-400/20"
          title="Mostrar Directorio"
        >
          <div className="rotate-180 [writing-mode:vertical-lr] flex items-center justify-center gap-3 whitespace-nowrap">
            <span className="text-[11px] font-black uppercase tracking-[0.2em] leading-none">Directorio</span>
            <Users size={16} className="-rotate-90 text-blue-200 group-hover:text-white transition-colors" />
          </div>
        </button>
      )}

      {/* Editor de Plantilla */}
      <div className="flex-1 flex flex-col min-w-0 bg-white rounded-[40px] shadow-[0_8px_40px_rgb(0,0,0,0.03)] border border-slate-100 overflow-hidden relative">
        {!selectedUser ? (
          <div className="flex-1 flex flex-col items-center justify-center text-slate-300 space-y-6 text-center p-20">
            <div className="w-32 h-32 rounded-[40px] bg-slate-50 flex items-center justify-center text-slate-100">
              <Target size={64} />
            </div>
            <div className="space-y-2">
              <h3 className="text-2xl font-black text-slate-400">Editor de Plantillas</h3>
              <p className="max-w-xs mx-auto text-sm font-medium">Selecciona una persona del directorio para configurar sus indicadores de desempeño anual.</p>
            </div>
          </div>
        ) : (
          <>
            <div className="p-8 border-b border-slate-50 flex flex-col md:flex-row md:items-center justify-between bg-white relative z-10 gap-6">
              <div className="flex items-center gap-4">
                <div className="h-12 w-12 bg-slate-50 rounded-[20px] border border-slate-100 flex items-center justify-center text-[#004C6C]">
                  <Target size={24} />
                </div>
                <div>
                  <h2 className="text-2xl font-black text-[#004C6C] tracking-tight">{selectedUser.name}</h2>
                  <div className="flex items-center gap-2 mt-1">
                    <span className="text-[10px] font-bold text-slate-400 uppercase tracking-widest">
                      {typeof selectedUser.area === 'object' ? selectedUser.area?.name : selectedUser.area}
                    </span>
                    <span className="h-1 w-1 bg-slate-300 rounded-full"></span>
                    <span className="text-[10px] font-bold text-slate-400 uppercase tracking-widest">
                      {typeof selectedUser.position === 'object' ? selectedUser.position?.name : selectedUser.position}
                    </span>
                  </div>
                </div>
              </div>

              <div className="flex items-center gap-4">
                <div className={`px-4 py-2 rounded-xl border flex flex-col items-center justify-center font-black ${totalWeight === 100 ? 'border-green-100 text-green-500 bg-green-50/30' : 'border-red-100 text-red-400 bg-red-50/30'}`}>
                  <span className="text-[8px] uppercase tracking-widest opacity-60 leading-none mb-1">Peso Total</span>
                  <span className="text-lg leading-none">{totalWeight}%</span>
                </div>

                <div className="flex gap-2">
                  <button
                    onClick={handleAddKPI}
                    className="flex items-center gap-2 px-5 py-3 bg-slate-50 text-slate-500 rounded-xl font-extrabold text-xs hover:bg-slate-100 transition-all uppercase tracking-wider"
                  >
                    <Plus size={16} /> Agregar
                  </button>
                  <button
                    onClick={handleSave}
                    disabled={isSaving}
                    className="flex items-center gap-2 px-6 py-3 bg-[#004C6C] text-white rounded-xl font-extrabold text-xs hover:bg-[#003a53] shadow-lg shadow-blue-900/10 transition-all uppercase tracking-wider disabled:opacity-50"
                  >
                    <Save size={16} /> {isSaving ? 'Guardando...' : 'Guardar'}
                  </button>
                </div>
              </div>
            </div>

            <div className="flex-1 overflow-hidden flex flex-col bg-slate-50/30">
              {/* Breadcrumbs / Navigation Bar */}
              <div className="px-8 py-3 bg-white border-b border-slate-50 flex items-center gap-3 text-[10px] font-black uppercase tracking-widest text-slate-400">
                <button
                  onClick={() => setActiveView('list')}
                  className={`hover:text-[#004C6C] transition-colors ${activeView === 'list' ? 'text-[#004C6C]' : ''}`}
                >
                  KPIs Overview
                </button>
                {activeKpiIdx !== null && (
                  <>
                    <span className="text-slate-200">/</span>
                    <button
                      onClick={() => setActiveView('kpi-edit')}
                      className={`hover:text-[#004C6C] transition-colors ${activeView === 'kpi-edit' ? 'text-[#004C6C]' : ''}`}
                    >
                      {localKpis[activeKpiIdx]?.name || 'Nuevo KPI'}
                    </button>
                  </>
                )}
                {activeIndIdx !== null && (
                  <>
                    <span className="text-slate-200">/</span>
                    <span className="text-[#004C6C]">
                      {localKpis[activeKpiIdx!]?.indicators?.[activeIndIdx]?.name || 'Nuevo Indicador'}
                    </span>
                  </>
                )}
              </div>

              <div className="flex-1 overflow-y-auto p-10 custom-scrollbar">
                {kpisLoading ? (
                  <div className="space-y-6">
                    <Skeleton className="h-40 w-full rounded-[32px]" />
                    <Skeleton className="h-40 w-full rounded-[32px]" />
                  </div>
                ) : activeView === 'list' ? (
                  // VIEW: KPI LIST
                  <div className="space-y-8 animate-fade-in">
                    {localKpis.length === 0 ? (
                      <div className="text-center py-24 bg-white rounded-[40px] border border-dashed border-slate-200 space-y-6">
                        <Plus className="w-16 h-16 text-slate-100 mx-auto" />
                        <div className="space-y-2">
                          <p className="text-xl font-bold text-slate-400">Sin KPIs configurados</p>
                          <p className="text-sm text-slate-300">Empieza agregando el primer indicador para este perfil.</p>
                        </div>
                        <button
                          onClick={handleAddKPI}
                          className="px-8 py-3 bg-slate-100 text-slate-500 rounded-xl font-black text-xs uppercase tracking-widest hover:bg-slate-200 transition-all"
                        >
                          Agregar mi primer KPI
                        </button>
                      </div>
                    ) : (
                      <div className="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-6">
                        {localKpis.map((kpi, idx) => (
                          <div key={idx} className="bg-white p-8 rounded-[32px] shadow-sm border border-slate-100 flex flex-col justify-between hover:shadow-xl hover:border-[#004C6C]/10 transition-all group relative">
                            <button
                              onClick={() => setIsRemoveModalOpen({ open: true, index: idx })}
                              className="absolute top-4 right-4 h-8 w-8 flex items-center justify-center bg-red-50 text-red-200 hover:text-red-500 rounded-lg transition-all opacity-0 group-hover:opacity-100"
                            >
                              <Trash2 size={14} />
                            </button>

                            <div className="space-y-4">
                              <div className="flex justify-between items-start">
                                <div className="h-10 w-10 bg-slate-50 rounded-xl flex items-center justify-center text-[#004C6C]">
                                  <Target size={20} />
                                </div>
                                <span className="text-[10px] font-black text-[#EE9D4C] bg-orange-50 px-3 py-1 rounded-full border border-orange-100">
                                  {kpi.weight}%
                                </span>
                              </div>
                              <div>
                                <h4 className="text-lg font-black text-slate-700 leading-tight group-hover:text-[#004C6C] transition-colors">{kpi.name || 'Sin nombre'}</h4>
                                <p className="text-[10px] font-bold text-slate-400 uppercase tracking-widest mt-1">{kpi.stage || 'Sin etapa'}</p>
                              </div>
                              <p className="text-xs text-slate-400 line-clamp-2 font-medium">{kpi.description || 'Sin definición'}</p>
                            </div>

                            <div className="mt-8 flex items-center justify-between pt-6 border-t border-slate-50">
                              <div className="flex -space-x-2">
                                {(kpi.indicators?.length || 0) > 0 ? (
                                  <div className="flex items-center gap-2">
                                    <div className="h-6 w-6 rounded-full bg-[#004C6C] text-white text-[8px] flex items-center justify-center font-black">
                                      {kpi.indicators?.length}
                                    </div>
                                    <span className="text-[9px] font-bold text-slate-400 uppercase tracking-widest">Indicadores</span>
                                  </div>
                                ) : (
                                  <span className="text-[9px] font-bold text-slate-300 uppercase tracking-widest italic">Sin indicadores</span>
                                )}
                              </div>
                              <button
                                onClick={() => { setActiveKpiIdx(idx); setActiveView('kpi-edit'); }}
                                className="px-4 py-2 bg-[#004C6C] text-white rounded-xl text-[10px] font-black uppercase tracking-widest hover:bg-[#003a53] transition-all"
                              >
                                Configurar
                              </button>
                            </div>
                          </div>
                        ))}
                      </div>
                    )}
                  </div>
                ) : activeView === 'kpi-edit' && activeKpiIdx !== null ? (
                  // VIEW: KPI CONFIGURATOR
                  <div className="max-w-4xl mx-auto space-y-10 animate-slide-in">
                    <div className="flex items-center gap-4 mb-2">
                      <button
                        onClick={() => setActiveView('list')}
                        className="h-10 w-10 rounded-xl bg-white border border-slate-100 flex items-center justify-center text-slate-400 hover:text-[#004C6C] transition-all"
                      >
                        <ArrowLeft size={18} />
                      </button>
                      <div>
                        <h3 className="text-xl font-black text-[#004C6C]">Configuración del KPI</h3>
                        <p className="text-xs font-bold text-slate-400 uppercase tracking-widest">Define las propiedades generales y sus métricas</p>
                      </div>
                    </div>

                    <div className="bg-white p-10 rounded-[40px] border border-slate-100 shadow-sm space-y-8">
                      <div className="grid grid-cols-1 md:grid-cols-2 gap-8">
                        <div className="space-y-3">
                          <label className="text-[10px] font-black text-slate-400 uppercase tracking-widest ml-1">Nombre del KPI</label>
                          <input
                            type="text"
                            value={localKpis[activeKpiIdx].name}
                            onChange={e => handleUpdateKPI(activeKpiIdx, { name: e.target.value })}
                            className="w-full bg-slate-50 border border-slate-100 rounded-2xl px-6 py-4 text-sm font-bold text-slate-700 focus:bg-white focus:border-[#004C6C] outline-none transition-all"
                            placeholder="Ej: Cumplimiento de Ventas"
                          />
                        </div>
                        <div className="space-y-3">
                          <label className="text-[10px] font-black text-slate-400 uppercase tracking-widest ml-1">Etapa / Proceso</label>
                          <input
                            type="text"
                            value={localKpis[activeKpiIdx].stage || ''}
                            onChange={e => handleUpdateKPI(activeKpiIdx, { stage: e.target.value })}
                            className="w-full bg-slate-50 border border-slate-100 rounded-2xl px-6 py-4 text-sm font-bold text-slate-700 focus:bg-white focus:border-[#EE9D4C] outline-none transition-all"
                            placeholder="Ej: Cierre Comercial"
                          />
                        </div>
                      </div>

                      <div className="space-y-3">
                        <label className="text-[10px] font-black text-slate-400 uppercase tracking-widest ml-1">Definición Estratégica</label>
                        <textarea
                          value={localKpis[activeKpiIdx].description || ''}
                          onChange={e => handleUpdateKPI(activeKpiIdx, { description: e.target.value })}
                          className="w-full bg-slate-50 border border-slate-100 rounded-2xl px-6 py-4 text-sm font-medium text-slate-600 focus:bg-white focus:border-[#004C6C] outline-none transition-all placeholder:text-slate-200 min-h-[120px] resize-none"
                        />
                      </div>

                      <div className="grid grid-cols-2 md:grid-cols-4 gap-6">
                        <div className="space-y-3">
                          <label className="text-[10px] font-black text-slate-400 uppercase tracking-widest ml-1">Meta Global</label>
                          <input
                            type="number"
                            value={localKpis[activeKpiIdx].target}
                            onChange={e => handleUpdateKPI(activeKpiIdx, { target: Number(e.target.value) })}
                            className="w-full bg-slate-50 border border-slate-100 rounded-2xl px-6 py-4 text-lg font-black text-[#004C6C]"
                            disabled={!!(localKpis[activeKpiIdx].indicators && localKpis[activeKpiIdx].indicators!.length > 0)}
                          />
                        </div>
                        <div className="space-y-3">
                          <label className="text-[10px] font-black text-slate-400 uppercase tracking-widest ml-1">Peso %</label>
                          <input
                            type="number"
                            value={localKpis[activeKpiIdx].weight}
                            onChange={e => handleUpdateKPI(activeKpiIdx, { weight: Number(e.target.value) })}
                            className="w-full bg-slate-50 border border-slate-100 rounded-2xl px-6 py-4 text-lg font-black text-[#EE9D4C]"
                          />
                        </div>
                        <div className="flex flex-col justify-end pb-1 md:col-span-2">
                          <button
                            onClick={() => handleUpdateKPI(activeKpiIdx, { lower_is_better: !localKpis[activeKpiIdx].lower_is_better })}
                            className={`flex items-center justify-center gap-3 px-6 py-4 rounded-2xl border-2 transition-all font-black text-[10px] uppercase tracking-widest ${localKpis[activeKpiIdx].lower_is_better
                              ? 'bg-orange-50 border-orange-200 text-orange-600'
                              : 'bg-slate-50 border-slate-100 text-slate-400'
                              }`}
                          >
                            <ShieldAlert size={16} />
                            {localKpis[activeKpiIdx].lower_is_better ? 'Menor es mejor' : 'Mayor es mejor'}
                          </button>
                        </div>
                      </div>
                    </div>

                    <div className="space-y-6">
                      <div className="flex items-center justify-between px-4">
                        <div className="flex items-center gap-3">
                          <div className="p-2 bg-[#004C6C] rounded-lg text-white"><List size={18} /></div>
                          <h4 className="text-sm font-black text-slate-700 uppercase tracking-widest">Indicadores Detallados</h4>
                        </div>
                        <button
                          onClick={() => handleAddIndicator(activeKpiIdx)}
                          className="px-6 py-3 bg-white border border-slate-200 text-[#004C6C] rounded-2xl font-black text-[10px] uppercase tracking-widest hover:bg-slate-50 transition-all flex items-center gap-2"
                        >
                          <Plus size={16} /> Nuevo Indicador
                        </button>
                      </div>

                      <div className="grid grid-cols-1 gap-4">
                        {localKpis[activeKpiIdx].indicators?.map((ind, iIdx) => (
                          <div key={iIdx} className="bg-white p-6 rounded-3xl border border-slate-100 shadow-sm flex items-center justify-between group hover:border-[#004C6C]/30 transition-all">
                            <div className="flex items-center gap-6">
                              <div className="h-12 w-12 bg-slate-50 rounded-2xl flex items-center justify-center text-slate-300 group-hover:bg-blue-50 group-hover:text-[#004C6C] transition-all">
                                <Target size={24} />
                              </div>
                              <div>
                                <p className="font-black text-slate-700">{ind.name || 'Indicador sin nombre'}</p>
                                <p className="text-[10px] text-slate-400 font-bold uppercase tracking-widest">{ind.definition || 'Sin definición'}</p>
                              </div>
                            </div>
                            <div className="flex items-center gap-3">
                              <button
                                onClick={() => handleRemoveIndicator(activeKpiIdx, iIdx)}
                                className="h-10 w-10 rounded-xl flex items-center justify-center text-slate-200 hover:text-red-500 hover:bg-red-50 transition-all"
                              >
                                <Trash2 size={18} />
                              </button>
                              <button
                                onClick={() => { setActiveIndIdx(iIdx); setActiveView('indicator-edit'); }}
                                className="px-6 py-2.5 bg-slate-50 text-slate-500 rounded-xl text-[10px] font-black uppercase tracking-widest hover:bg-[#004C6C] hover:text-white transition-all shadow-sm"
                              >
                                Configurar Métricas
                              </button>
                            </div>
                          </div>
                        ))}
                        {(!localKpis[activeKpiIdx].indicators || localKpis[activeKpiIdx].indicators!.length === 0) && (
                          <div className="text-center py-12 border-2 border-dashed border-slate-100 rounded-[40px] text-slate-300">
                            <p className="text-sm font-bold">No hay indicadores. Agrega uno para desglosar este KPI.</p>
                          </div>
                        )}
                      </div>
                    </div>
                  </div>
                ) : activeView === 'indicator-edit' && activeKpiIdx !== null && activeIndIdx !== null ? (
                  // VIEW: INDICATOR CONFIGURATOR
                  <div className="max-w-4xl mx-auto space-y-10 animate-slide-in">
                    <div className="flex items-center gap-4">
                      <button
                        onClick={() => setActiveView('kpi-edit')}
                        className="h-12 w-12 rounded-2xl bg-white border border-slate-100 flex items-center justify-center text-slate-400 hover:text-[#004C6C] transition-all shadow-sm"
                      >
                        <ArrowLeft size={20} />
                      </button>
                      <div>
                        <p className="text-[10px] font-black text-slate-300 uppercase tracking-[0.2em] mb-1">
                          KPI: {localKpis[activeKpiIdx].name}
                        </p>
                        <h3 className="text-2xl font-black text-[#004C6C]">Detalle del Indicador</h3>
                      </div>
                    </div>

                    <div className="bg-white p-12 rounded-[50px] border border-slate-100 shadow-sm space-y-10">
                      <div className="grid grid-cols-1 md:grid-cols-2 gap-10">
                        <div className="space-y-4">
                          <label className="text-[11px] font-black text-slate-400 uppercase tracking-widest ml-1">Nombre</label>
                          <input
                            type="text"
                            value={localKpis[activeKpiIdx!].indicators![activeIndIdx!].name}
                            onChange={e => handleUpdateIndicator(activeKpiIdx!, activeIndIdx!, { name: e.target.value })}
                            className="w-full bg-slate-50/50 border border-slate-100 rounded-3xl px-8 py-5 text-lg font-black text-slate-700 focus:bg-white focus:border-[#004C6C] outline-none transition-all"
                            placeholder="Ej: Cumplimiento de Ventas"
                          />
                        </div>
                        <div className="space-y-4">
                          <label className="text-[11px] font-black text-slate-400 uppercase tracking-widest ml-1">Definición</label>
                          <input
                            type="text"
                            value={localKpis[activeKpiIdx!].indicators![activeIndIdx!].definition}
                            onChange={e => handleUpdateIndicator(activeKpiIdx!, activeIndIdx!, { definition: e.target.value })}
                            className="w-full bg-slate-50/50 border border-slate-100 rounded-3xl px-8 py-5 text-sm font-bold text-slate-600 focus:bg-white transition-all outline-none"
                          />
                        </div>
                      </div>

                      <div className="grid grid-cols-1 md:grid-cols-2 gap-10">
                        <div className="bg-slate-50 p-8 rounded-[40px] space-y-6">
                          <div className="flex items-center gap-3">
                            <div className="p-2 bg-[#004C6C] rounded-xl text-white"><Calculator size={18} /></div>
                            <h4 className="font-black text-slate-700 uppercase tracking-widest text-xs">Cálculo y Meta</h4>
                          </div>
                          <div className="space-y-4">
                            <div className="space-y-2">
                              <label className="text-[9px] font-black text-slate-400 uppercase tracking-widest ml-1">Fórmula Matemática</label>
                              <input
                                type="text"
                                value={localKpis[activeKpiIdx!].indicators![activeIndIdx!].formula}
                                onChange={e => handleUpdateIndicator(activeKpiIdx!, activeIndIdx!, { formula: e.target.value })}
                                className="w-full bg-white border border-slate-200 rounded-2xl px-6 py-4 text-sm font-mono font-bold text-[#004C6C] focus:ring-4 focus:ring-blue-50 outline-none transition-all"
                              />
                            </div>
                            <div className="space-y-2">
                              <label className="text-[9px] font-black text-slate-400 uppercase tracking-widest ml-1">Meta Fija Esperada</label>
                              <input
                                type="number"
                                value={localKpis[activeKpiIdx!].indicators![activeIndIdx!].fixed_goal}
                                onChange={e => handleUpdateIndicator(activeKpiIdx!, activeIndIdx!, { fixed_goal: Number(e.target.value) })}
                                className="w-full bg-white border border-slate-200 rounded-2xl px-6 py-4 text-xl font-black text-slate-700 outline-none"
                              />
                            </div>
                          </div>
                        </div>

                        <div className="bg-slate-50 p-8 rounded-[40px] space-y-6">
                          <div className="flex items-center justify-between">
                            <div className="flex items-center gap-3">
                              <div className="p-2 bg-[#EE9D4C] rounded-xl text-white"><Settings2 size={18} /></div>
                              <h4 className="font-black text-slate-700 uppercase tracking-widest text-xs">Variables / Parámetros</h4>
                            </div>
                            <button
                              onClick={() => handleAddParameter(activeKpiIdx, activeIndIdx)}
                              className="text-[9px] font-black text-blue-600 hover:underline"
                            >
                              + Agregar Variable
                            </button>
                          </div>
                          <div className="space-y-3">
                            {localKpis[activeKpiIdx!].indicators![activeIndIdx!].parameters?.map((param, pIdx) => (
                              <div key={pIdx} className="flex gap-3 items-center bg-white p-3 rounded-2xl border border-slate-100 shadow-sm">
                                <input
                                  type="text"
                                  value={param.name}
                                  onChange={e => handleUpdateParameter(activeKpiIdx!, activeIndIdx!, pIdx, { name: e.target.value })}
                                  placeholder="Ej: Ventas"
                                  className="flex-1 bg-transparent border-none text-[11px] font-bold text-slate-600 outline-none"
                                />
                                <button
                                  onClick={() => handleRemoveParameter(activeKpiIdx, activeIndIdx, pIdx)}
                                  className="text-red-200 hover:text-red-500 transition-colors"
                                >
                                  <Trash2 size={14} />
                                </button>
                              </div>
                            ))}
                          </div>
                        </div>
                      </div>

                      <div className="space-y-6">
                        <div className="flex items-center justify-between px-2">
                          <div className="flex items-center gap-3">
                            <div className="p-2 bg-green-100 text-green-600 rounded-xl"><Info size={18} /></div>
                            <h4 className="text-xs font-black text-slate-700 uppercase tracking-widest">Niveles de Cumplimiento (Meta Condicional)</h4>
                          </div>
                          <button
                            onClick={() => handleAddGoal(activeKpiIdx, activeIndIdx)}
                            className="text-[9px] font-black text-green-600 hover:underline"
                          >
                            + Agregar Nivel
                          </button>
                        </div>

                        <div className="space-y-4">
                          {localKpis[activeKpiIdx!].indicators![activeIndIdx!].conditional_goals?.map((goal, gIdx) => (
                            <div
                              key={gIdx}
                              className="group flex flex-col xl:flex-row items-start xl:items-center gap-6 bg-white p-6 rounded-[32px] border border-slate-100 shadow-sm hover:shadow-xl hover:border-blue-100 transition-all duration-300 relative overflow-hidden"
                            >
                              {/* Indicador de Color Lateral */}
                              <div className={`absolute left-0 top-0 bottom-0 w-2 ${goal.color === 'excellent' || goal.color === 'optimal' ? 'bg-green-500' :
                                goal.color === 'acceptable' ? 'bg-yellow-400' :
                                  goal.color === 'at_risk' ? 'bg-orange-500' :
                                    'bg-red-500'
                                }`} />

                              {/* Nombre y Color */}
                              <div className="flex items-center gap-4 min-w-[240px]">
                                <div className={`h-10 w-10 rounded-xl flex items-center justify-center text-white shadow-sm ${goal.color === 'excellent' || goal.color === 'optimal' ? 'bg-green-500' :
                                  goal.color === 'acceptable' ? 'bg-yellow-400' :
                                    goal.color === 'at_risk' ? 'bg-orange-500' :
                                      'bg-red-500'
                                  }`}>
                                  <Target size={18} />
                                </div>
                                <div className="flex-1 space-y-1">
                                  <input
                                    type="text"
                                    value={goal.level}
                                    onChange={e => {
                                      const updatedGoals = [...localKpis[activeKpiIdx!].indicators![activeIndIdx!].conditional_goals];
                                      updatedGoals[gIdx].level = e.target.value;
                                      handleUpdateIndicator(activeKpiIdx!, activeIndIdx!, { conditional_goals: updatedGoals });
                                    }}
                                    className="w-full bg-transparent border-none p-0 text-xs font-black text-slate-700 uppercase tracking-widest outline-none focus:ring-0"
                                    placeholder="Nivel"
                                  />
                                  <select
                                    value={goal.color}
                                    onChange={e => {
                                      const updatedGoals = [...localKpis[activeKpiIdx!].indicators![activeIndIdx!].conditional_goals];
                                      updatedGoals[gIdx].color = e.target.value as any;
                                      handleUpdateIndicator(activeKpiIdx!, activeIndIdx!, { conditional_goals: updatedGoals });
                                    }}
                                    className="bg-slate-50 border-none rounded-lg px-2 py-0.5 text-[9px] font-bold text-slate-400 outline-none cursor-pointer hover:bg-slate-100 transition-colors"
                                  >
                                    <option value="optimal">Verde</option>
                                    <option value="acceptable">Amarillo</option>
                                    <option value="at_risk">Naranja</option>
                                    <option value="deficient">Rojo</option>
                                  </select>
                                </div>
                              </div>

                              {/* Rangos */}
                              <div className="flex items-center gap-3 bg-slate-50/50 p-2 rounded-2xl border border-slate-50">
                                <div className="flex flex-col gap-0.5 px-3">
                                  <span className="text-[8px] font-black text-slate-300 uppercase">Mínimo</span>
                                  <input
                                    type="number"
                                    step="0.01"
                                    value={goal.min_value}
                                    onChange={e => {
                                      const updatedGoals = [...localKpis[activeKpiIdx!].indicators![activeIndIdx!].conditional_goals];
                                      updatedGoals[gIdx].min_value = Number(e.target.value);
                                      handleUpdateIndicator(activeKpiIdx!, activeIndIdx!, { conditional_goals: updatedGoals });
                                    }}
                                    className="w-16 bg-transparent border-none p-0 text-sm font-black text-[#004C6C] outline-none"
                                  />
                                </div>
                                <div className="h-8 w-1px bg-slate-200" />
                                <div className="flex flex-col gap-0.5 px-3">
                                  <span className="text-[8px] font-black text-slate-300 uppercase">Máximo</span>
                                  <input
                                    type="number"
                                    step="0.01"
                                    value={goal.max_value}
                                    onChange={e => {
                                      const updatedGoals = [...localKpis[activeKpiIdx!].indicators![activeIndIdx!].conditional_goals];
                                      updatedGoals[gIdx].max_value = Number(e.target.value);
                                      handleUpdateIndicator(activeKpiIdx!, activeIndIdx!, { conditional_goals: updatedGoals });
                                    }}
                                    className="w-16 bg-transparent border-none p-0 text-sm font-black text-[#004C6C] outline-none"
                                  />
                                </div>
                              </div>

                              {/* Puntaje */}
                              <div className="flex flex-col gap-1 min-w-[100px]">
                                <span className="text-[8px] font-black text-slate-300 uppercase tracking-widest">Puntaje %</span>
                                <div className="flex items-center gap-2">
                                  <input
                                    type="number"
                                    value={goal.score || 0}
                                    onChange={e => {
                                      const updatedGoals = [...localKpis[activeKpiIdx!].indicators![activeIndIdx!].conditional_goals];
                                      updatedGoals[gIdx].score = Number(e.target.value);
                                      handleUpdateIndicator(activeKpiIdx!, activeIndIdx!, { conditional_goals: updatedGoals });
                                    }}
                                    className="w-12 bg-transparent border-none p-0 text-lg font-black text-slate-700 outline-none"
                                  />
                                  <span className="text-xs font-bold text-slate-300">%</span>
                                </div>
                              </div>

                              {/* Análisis / Feedback */}
                              <div className="flex-1 w-full xl:w-auto">
                                <textarea
                                  value={goal.qualification}
                                  onChange={e => {
                                    const updatedGoals = [...localKpis[activeKpiIdx!].indicators![activeIndIdx!].conditional_goals];
                                    updatedGoals[gIdx].qualification = e.target.value;
                                    handleUpdateIndicator(activeKpiIdx!, activeIndIdx!, { conditional_goals: updatedGoals });
                                  }}
                                  className="w-full bg-slate-50/50 border border-transparent hover:border-slate-100 rounded-2xl px-4 py-3 text-[10px] font-bold text-slate-500 outline-none focus:bg-white focus:ring-1 focus:ring-blue-100 transition-all resize-none leading-relaxed h-14"
                                  placeholder="Evaluación para este nivel..."
                                />
                              </div>

                              {/* Acciones */}
                              <button
                                onClick={() => handleRemoveGoal(activeKpiIdx!, activeIndIdx!, gIdx)}
                                className="p-3 text-slate-200 hover:text-red-500 hover:bg-red-50 rounded-2xl transition-all"
                              >
                                <Trash2 size={18} />
                              </button>
                            </div>
                          ))}
                        </div>
                      </div>
                    </div>

                    <div className="flex justify-center pb-10">
                      <button
                        onClick={() => setActiveView('kpi-edit')}
                        className="px-12 py-4 bg-[#004C6C] text-white rounded-3xl font-black text-sm uppercase tracking-widest shadow-xl shadow-blue-900/20 hover:scale-[1.02] transition-all"
                      >
                        Confirmar y Regresar
                      </button>
                    </div>
                  </div>
                ) : null}
              </div>
            </div>
          </>
        )}
      </div>

      <Modal
        isOpen={isRemoveModalOpen.open}
        onClose={() => setIsRemoveModalOpen({ open: false, index: null })}
        onConfirm={handleRemoveKPI}
        title="¿Eliminar indicador?"
        message="¿Estás seguro de que deseas eliminar este KPI de la plantilla? Los cambios solo se harán permanentes al guardar la plantilla."
        confirmText="Sí, eliminar"
        cancelText="Conservar"
        type="danger"
      />
    </div>
  );
}
