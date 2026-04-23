import { useState, useEffect } from 'react';
import { useNavigate } from 'react-router-dom';
import { 
  Users, 
  ClipboardCheck, 
  TrendingUp, 
  AlertTriangle, 
  Clock,
  ChevronRight
} from 'lucide-react';
import { useUsers } from '../../users/hooks/useUsers';
import { evaluationService } from '../../evaluacion/services/evaluationService';
import type { User } from '../../users/types';
import type { Evaluation } from '../../evaluacion/types';
import { Skeleton } from '../../../components/ui/Skeleton';

export default function DashboardPage() {
  const navigate = useNavigate();
  const { users, isLoading: usersLoading } = useUsers();
  const [monthEvaluations, setMonthEvaluations] = useState<Evaluation[]>([]);
  const [isLoadingHistory, setIsLoadingHistory] = useState(true);
  
  const now = new Date();
  const currentMonth = now.getMonth() + 1;
  const currentYear = now.getFullYear();

  const meses = [
    'Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio', 
    'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'
  ];

  useEffect(() => {
    const fetchHistory = async () => {
      setIsLoadingHistory(true);
      try {
        const data = await evaluationService.getAllHistory({ 
          month: currentMonth, 
          year: currentYear 
        });
        setMonthEvaluations(data);
      } catch (error) {
        console.error("Error fetching dashboard history:", error);
      } finally {
        setIsLoadingHistory(false);
      }
    };

    fetchHistory();
  }, [currentMonth, currentYear]);

  // Calculations
  const totalUsers = users.length;
  const evaluatedCount = monthEvaluations.length;
  const progressPercent = totalUsers > 0 ? (evaluatedCount / totalUsers) * 100 : 0;
  
  const averageScore = evaluatedCount > 0 
    ? monthEvaluations.reduce((acc, curr) => acc + Number(curr.total_score || 0), 0) / evaluatedCount 
    : 0;

  const lowPerformanceCount = monthEvaluations.filter(e => Number(e.total_score || 0) < 70).length;

  // Group users by area
  const groupedData = users.reduce((acc, user) => {
    const areaName = typeof user.area === 'object' ? user.area?.name : user.area;
    const key = areaName || 'Sin Área';
    if (!acc[key]) acc[key] = [];
    const evaluation = monthEvaluations.find(e => e.user_id === user.id);
    acc[key].push({ user, evaluation });
    return acc;
  }, {} as Record<string, { user: User, evaluation?: Evaluation }[]>);

  const handleUserClick = (userId: number) => {
    navigate('/app/evaluacion', { 
      state: { 
        userId, 
        month: currentMonth, 
        year: currentYear 
      } 
    });
  };

  if (usersLoading || isLoadingHistory) {
     return (
        <div className="space-y-8 animate-fade-in">
           <div className="grid grid-cols-1 md:grid-cols-4 gap-6">
              {[1,2,3,4].map(i => <Skeleton key={i} className="h-32 rounded-[28px]" />)}
           </div>
           <Skeleton className="h-40 rounded-[28px]" />
           <div className="grid grid-cols-1 md:grid-cols-2 gap-8">
              {[1,2].map(i => <Skeleton key={i} className="h-96 rounded-[28px]" />)}
           </div>
        </div>
     );
  }

  return (
    <div className="max-w-7xl mx-auto space-y-8 pb-20 animate-fade-in">
      
      {/* Welcome Header */}
      <div className="flex flex-col md:flex-row md:items-end justify-between gap-4">
        <div>
          <h1 className="text-4xl font-black text-[#004C6C] tracking-tight">Dashboard KPIs</h1>
          <p className="text-slate-400 font-bold uppercase tracking-[0.2em] mt-1">
            {meses[currentMonth - 1]} {currentYear} — Resumen General
          </p>
        </div>
      </div>

      {/* Stats Cards */}
      <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6">
        <div className="bg-white p-6 rounded-[28px] border border-slate-100 shadow-sm flex items-center gap-5 group hover:border-[#004C6C]/20 transition-all">
          <div className="h-14 w-14 bg-blue-50 rounded-2xl flex items-center justify-center text-[#004C6C] transition-transform group-hover:scale-110">
            <Users size={24} />
          </div>
          <div>
             <h2 className="text-2xl font-black text-[#004C6C]">{totalUsers}</h2>
             <p className="text-[10px] font-bold text-slate-400 uppercase tracking-widest">Personas totales</p>
          </div>
        </div>

        <div className="bg-white p-6 rounded-[28px] border border-slate-100 shadow-sm flex items-center gap-5 group hover:border-green-100 transition-all">
          <div className="h-14 w-14 bg-green-50 rounded-2xl flex items-center justify-center text-green-600 transition-transform group-hover:scale-110">
            <ClipboardCheck size={24} />
          </div>
          <div>
             <div className="flex items-baseline gap-2">
               <h2 className="text-2xl font-black text-green-600">{evaluatedCount}</h2>
               <span className="text-xs font-bold text-green-400">({progressPercent.toFixed(0)}%)</span>
             </div>
             <p className="text-[10px] font-bold text-slate-400 uppercase tracking-widest">Evaluadas</p>
          </div>
        </div>

        <div className="bg-white p-6 rounded-[28px] border border-slate-100 shadow-sm flex items-center gap-5 group hover:border-blue-100 transition-all">
          <div className="h-14 w-14 bg-slate-50 rounded-2xl flex items-center justify-center text-[#004C6C] transition-transform group-hover:scale-110">
            <TrendingUp size={24} />
          </div>
          <div>
             <h2 className="text-2xl font-black text-[#004C6C]">{averageScore.toFixed(1)}%</h2>
             <p className="text-[10px] font-bold text-slate-400 uppercase tracking-widest">Promedio general</p>
          </div>
        </div>

        <div className="bg-white p-6 rounded-[28px] border border-slate-100 shadow-sm flex items-center gap-5 group hover:border-red-100 transition-all">
          <div className="h-14 w-14 bg-red-50 rounded-2xl flex items-center justify-center text-red-500 transition-transform group-hover:scale-110">
            <AlertTriangle size={24} />
          </div>
          <div>
             <h2 className="text-2xl font-black text-red-500">{lowPerformanceCount}</h2>
             <p className="text-[10px] font-bold text-slate-400 uppercase tracking-widest">Bajo rendimiento</p>
          </div>
        </div>
      </div>

      {/* Global Progress Bar */}
      <div className="bg-white p-8 rounded-[28px] border border-slate-100 shadow-sm space-y-4">
        <div className="flex justify-between items-end">
          <h3 className="text-sm font-black text-[#004C6C] uppercase tracking-widest">Progreso de evaluaciones del mes</h3>
          <p className="text-xs font-bold text-slate-400 uppercase tracking-widest">
            <span className="text-[#004C6C] text-sm font-black">{evaluatedCount}</span> / {totalUsers}
          </p>
        </div>
        <div className="h-3 w-full bg-slate-100 rounded-full overflow-hidden">
          <div 
            className="h-full bg-[#004C6C] rounded-full transition-all duration-1000 ease-out shadow-lg shadow-blue-900/20"
            style={{ width: `${progressPercent}%` }}
          ></div>
        </div>
      </div>

      {/* Area Cards Grid */}
      <div className="grid grid-cols-1 lg:grid-cols-2 gap-8">
        {Object.entries(groupedData).map(([area, members], areaIdx) => {
          const areaCount = members.length;
          const areaEvaluated = members.filter(m => m.evaluation).length;
          const areaAverage = areaEvaluated > 0 
            ? members.reduce((acc, m) => acc + Number(m.evaluation?.total_score || 0), 0) / areaEvaluated 
            : 0;

          return (
            <div key={area} className={`bg-white rounded-[28px] border border-slate-200 overflow-hidden shadow-sm animate-slide-up stagger-${(areaIdx % 5) + 1}`}>
              <div className="p-6 border-b border-slate-50 flex items-center justify-between bg-slate-50/30">
                <div>
                  <h3 className="text-lg font-black text-[#004C6C] tracking-tight">{area}</h3>
                  <p className="text-[10px] font-bold text-slate-400 uppercase tracking-widest">
                    {areaEvaluated}/{areaCount} evaluadas
                  </p>
                </div>
                {areaEvaluated > 0 && (
                  <div className={`px-3 py-1.5 rounded-xl text-xs font-black border ${
                    areaAverage >= 90 ? 'bg-green-50 border-green-100 text-green-600' :
                    areaAverage >= 70 ? 'bg-orange-50 border-orange-100 text-orange-600' :
                    'bg-red-50 border-red-100 text-red-600'
                  }`}>
                    {areaAverage.toFixed(1)}%
                  </div>
                )}
              </div>

              <div className="divide-y divide-slate-50">
                {members.map(({ user, evaluation }) => (
                  <div 
                    key={user.id} 
                    className="p-5 flex items-center justify-between hover:bg-slate-50/50 transition-colors group cursor-pointer"
                    onClick={() => handleUserClick(user.id)}
                  >
                    <div className="flex-1 min-w-0">
                      <p className="text-sm font-black text-slate-700 truncate group-hover:text-[#004C6C] transition-colors">{user.name}</p>
                      <p className="text-[10px] font-bold text-slate-400 uppercase tracking-widest truncate">
                        {typeof user.position === 'object' ? user.position?.name : user.position}
                      </p>
                    </div>
                    
                    <div className="flex items-center gap-4">
                      {evaluation ? (
                        <div className={`px-2.5 py-1 rounded-lg text-[10px] font-black border ${
                          Number(evaluation.total_score) >= 90 ? 'bg-green-50 border-green-100 text-green-600' :
                          Number(evaluation.total_score) >= 70 ? 'bg-orange-50 border-orange-100 text-orange-600' :
                          'bg-red-50 border-red-100 text-red-600'
                        }`}>
                          {Number(evaluation.total_score).toFixed(1)}%
                        </div>
                      ) : (
                        <div className="flex items-center gap-1.5 px-3 py-1 bg-white border border-slate-100 rounded-lg text-slate-300 text-[10px] font-black uppercase tracking-widest group-hover:border-orange-200 group-hover:text-[#EE9D4C] transition-all">
                           <Clock size={12} />
                           Pendiente
                        </div>
                      )}
                      <div className="h-8 w-8 rounded-lg flex items-center justify-center text-slate-200 group-hover:bg-[#004C6C] group-hover:text-white transition-all group-hover:translate-x-1">
                        <ChevronRight size={18} />
                      </div>
                    </div>
                  </div>
                ))}
              </div>
            </div>
          );
        })}
      </div>
    </div>
  );
}
