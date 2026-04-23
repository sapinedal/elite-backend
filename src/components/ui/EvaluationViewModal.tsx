import React from 'react';
import { X, Calendar, User, Layout, TrendingUp, Info } from 'lucide-react';
import { KPIDetailTable } from '../../modules/evaluacion/components/KPIDetailTable';
import { meses } from '../../modules/evaluacion/types';
import type { Evaluation } from '../../modules/evaluacion/types';
import { Portal } from './Portal';

interface EvaluationViewModalProps {
  isOpen: boolean;
  onClose: () => void;
  evaluation: Evaluation | null;
}

export const EvaluationViewModal: React.FC<EvaluationViewModalProps> = ({ isOpen, onClose, evaluation }) => {
  if (!evaluation) return null;

  const getScoreColor = (score: number) => {
    if (score >= 90) return 'text-green-600 bg-green-50 border-green-200';
    if (score >= 70) return 'text-yellow-600 bg-yellow-50 border-yellow-200';
    return 'text-red-600 bg-red-50 border-red-200';
  };

  return (
    <Portal isOpen={isOpen}>
      <div className="fixed inset-0 z-100 flex items-center justify-center p-4 bg-slate-900/60 transition-opacity duration-300">
        <div className={`bg-[#f8fafc] w-full max-w-5xl max-h-[90vh] rounded-[40px] shadow-2xl overflow-hidden flex flex-col transition-transform duration-300 ${isOpen ? 'scale-100' : 'scale-95'}`}>

          {/* Header Modal */}
          <div className="bg-white p-8 border-b border-slate-100 flex items-center justify-between shadow-sm relative z-10">
            <div className="flex items-center gap-6">
              <div className={`h-14 w-14 rounded-2xl flex items-center justify-center text-white shadow-lg ${getScoreColor(Number(evaluation.total_score || 0)).split(' ')[0].replace('text', 'bg')}`}>
                <TrendingUp size={28} />
              </div>
              <div>
                <h2 className="text-2xl font-black text-[#004C6C] tracking-tight">Detalle de Evaluación</h2>
                <div className="flex items-center gap-3 mt-1">
                  <span className="text-xs font-bold text-slate-400 flex items-center gap-1 uppercase tracking-widest">
                    <Calendar size={12} /> {meses.find(m => m.value === evaluation.month)?.label} {evaluation.year}
                  </span>
                  <span className="h-1 w-1 bg-slate-300 rounded-full"></span>
                  <span className="text-xs font-bold text-slate-400 flex items-center gap-1 uppercase tracking-widest">
                    ID: #{evaluation.id}
                  </span>
                </div>
              </div>
            </div>
            <button onClick={onClose} className="p-3 text-slate-400 hover:bg-slate-50 rounded-2xl transition-all">
              <X size={24} />
            </button>
          </div>

          {/* Content Scrollable */}
          <div className="flex-1 overflow-y-auto p-10 space-y-10">

            {/* Summary Banner */}
            <div className="grid grid-cols-1 md:grid-cols-3 gap-6">
              <div className="bg-white p-6 rounded-3xl border border-slate-100 shadow-sm flex items-center gap-5">
                <div className="h-12 w-12 bg-blue-50 text-blue-500 rounded-xl flex items-center justify-center">
                  <User size={24} />
                </div>
                <div>
                  <p className="text-[10px] font-black text-slate-400 uppercase tracking-widest">Colaborador</p>
                  <p className="font-bold text-slate-700">Verificando...</p>
                </div>
              </div>
              <div className="bg-white p-6 rounded-3xl border border-slate-100 shadow-sm flex items-center gap-5">
                <div className="h-12 w-12 bg-orange-50 text-orange-500 rounded-xl flex items-center justify-center">
                  <Layout size={24} />
                </div>
                <div>
                  <p className="text-[10px] font-black text-slate-400 uppercase tracking-widest">Estado</p>
                  <p className="font-bold text-slate-700 uppercase text-xs tracking-wider">{evaluation.status}</p>
                </div>
              </div>
              <div className={`p-6 rounded-3xl border shadow-sm flex flex-col items-center justify-center ${getScoreColor(Number(evaluation.total_score || 0))}`}>
                <p className="text-[10px] font-black uppercase tracking-widest opacity-60 mb-1">Resultado Final</p>
                <p className="text-3xl font-black leading-none">{Number(evaluation.total_score || 0).toFixed(1)}%</p>
              </div>
            </div>

            {/* Results List */}
            <div className="space-y-6">
              <h3 className="text-sm font-black text-slate-400 uppercase tracking-[0.2em] ml-2">Desglose de Indicadores</h3>
              <div className="grid grid-cols-1 gap-4">
                {evaluation.results?.map((res, idx) => (
                  <div key={idx} className="bg-white p-6 rounded-[32px] border border-slate-100 hover:border-slate-200 transition-all group">
                    <div className="flex flex-col md:flex-row justify-between gap-6">
                      <div className="space-y-1 flex-1">
                        <h4 className="font-black text-slate-800 tracking-tight">{res.kpi_name}</h4>
                        <p className="text-xs text-slate-400 font-medium italic">Peso en la evaluación: {res.kpi_weight}%</p>
                      </div>
                      <div className="flex gap-8 items-center">
                        <div className="text-center">
                          <p className="text-[9px] font-black text-slate-300 uppercase tracking-widest mb-1">Meta</p>
                          <p className="text-sm font-bold text-slate-600">{res.kpi_target}{res.kpi_unit}</p>
                        </div>
                        <div className="text-center">
                          <p className="text-[9px] font-black text-slate-300 uppercase tracking-widest mb-1">Real</p>
                          <p className="text-sm font-bold text-[#004C6C]">{res.real_value}{res.kpi_unit}</p>
                        </div>
                        <div className={`px-4 py-2 rounded-xl border font-black text-sm ${getScoreColor(Number(res.score || 0))}`}>
                          {Number(res.score || 0).toFixed(0)}%
                        </div>
                      </div>
                    </div>

                    {res.tablaDetalle && (
                      <div className="mt-6 pt-6 border-t border-slate-50">
                        <div className="flex items-center gap-2 mb-4 text-[#EE9D4C]">
                          <Info size={14} />
                          <span className="text-[10px] font-black uppercase tracking-widest">Evidencia y Desglose</span>
                        </div>
                        <KPIDetailTable data={res.tablaDetalle} onChange={() => { }} />
                      </div>
                    )}
                  </div>
                ))}
              </div>
            </div>

            {/* Analysis Section */}
            {evaluation.general_analysis && (
              <div className="bg-white p-8 rounded-[40px] border border-slate-100 space-y-4">
                <h3 className="text-sm font-black text-slate-400 uppercase tracking-[0.2em]">Análisis General</h3>
                <p className="text-slate-600 font-medium leading-relaxed italic">
                  "{evaluation.general_analysis}"
                </p>
              </div>
            )}

          </div>

          {/* Footer */}
          <div className="p-8 bg-white border-t border-slate-100 flex justify-end">
            <button
              onClick={onClose}
              className="px-8 py-4 bg-slate-100 text-slate-500 rounded-2xl font-bold hover:bg-slate-200 transition-all"
            >
              Cerrar Vista
            </button>
          </div>
        </div>
      </div>
    </Portal>
  );
};
