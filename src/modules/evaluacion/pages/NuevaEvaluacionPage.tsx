import { useState, useEffect } from 'react';
import { useUsers } from '../../users/hooks/useUsers';
import { useLocation } from 'react-router-dom';
import { evaluationService } from '../services/evaluationService';
import { meses } from '../types';
import type { Evaluation, EvaluationResult } from '../types';
import { calculateScore, calculateTotalScore } from '../utils/calculations';
import { Save, FileCheck, Info, AlertCircle, CheckCircle, Sparkles, Loader2, List } from 'lucide-react';
import { KPIDetailTable } from '../components/KPIDetailTable';
import { userService } from '../../users/services/userService';
import { CustomSelect } from '../../../components/ui/CustomSelect';
import { Autocomplete } from '../../../components/ui/Autocomplete';
import { useNotification } from '../../../context/NotificationContext';
import { Collapse } from '../../../components/ui/Collapse';
import { Modal } from '../../../components/ui/Modal';
import { Skeleton } from '../../../components/ui/Skeleton';
import ReactMarkdown from 'react-markdown';

export default function NuevaEvaluacionPage() {
  const { users } = useUsers();
  const { showNotification } = useNotification();

  const location = useLocation();

  // Selection state (con soporte para navegación desde dashboard)
  const [selectedUserId, setSelectedUserId] = useState<number | null>(location.state?.userId || null);
  const [selectedMonth, setSelectedMonth] = useState<number>(location.state?.month || new Date().getMonth() + 1);
  const [selectedYear, setSelectedYear] = useState<number>(location.state?.year || new Date().getFullYear());

  // Evaluation state
  const [evaluation, setEvaluation] = useState<Partial<Evaluation> | null>(null);
  const [isDataLoading, setIsDataLoading] = useState(false);
  const [isSaving, setIsSaving] = useState(false);
  const [isFinalizeModalOpen, setIsFinalizeModalOpen] = useState(false);
  const [isGeneratingAI, setIsGeneratingAI] = useState(false);


  const selectedUser = users.find(u => u.id === selectedUserId);

  // Load KPIs or existing evaluation when selection changes
  useEffect(() => {
    if (selectedUserId) {
      const loadData = async () => {
        setIsDataLoading(true);
        try {
          // 1. Intentar cargar evaluación existente
          const existing = await evaluationService.getEvaluation(selectedUserId, selectedMonth, selectedYear);

          if (existing && existing.id) {
            // Normalizar datos numéricos (pueden venir como strings desde la DB)
            const normalized: Evaluation = {
              ...existing,
              total_score: Number(existing.total_score || 0),
              results: existing.results?.map(r => ({
                ...r,
                kpi_weight: Number(r.kpi_weight || 0),
                kpi_target: Number(r.kpi_target || 0),
                real_value: r.real_value !== null ? Number(r.real_value) : null,
                score: Number(r.score || 0),
                // Asegurar que cargamos los detalles si vienen del backend como 'details' o 'tablaDetalle'
                tablaDetalle: (r as any).details?.tablaDetalle || (r.tablaDetalle && (r.tablaDetalle as any).headers ? r.tablaDetalle : null),
                indicator_results: (r as any).details?.indicator_results || [],
                ai_analysis: (r as any).details?.ai_analysis || null
              }))
            };
            setEvaluation(normalized);
          } else {
            // 2. Si no hay (o es un objeto vacío), cargar KPIs de la persona para crear nueva
            const userWithKpis = await userService.getUserById(selectedUserId);

            if (userWithKpis.kpis && userWithKpis.kpis.length > 0) {
              const initialResults: EvaluationResult[] = userWithKpis.kpis.map((kpi: any) => ({
                kpi_id: kpi.id,
                kpi_name: kpi.name,
                kpi_weight: Number(kpi.weight),
                kpi_target: Number(kpi.target),
                kpi_unit: kpi.unit,
                lower_is_better: !!kpi.lower_is_better,
                real_value: null,
                score: 0,
                indicator_results: kpi.indicators?.map((ind: any) => {
                  const initialVariables = ind.parameters?.reduce((acc: any, p: any) => ({ ...acc, [p.name]: p.value }), {}) || {};

                  // Calcular valor inicial si es posible
                  let initialCalculated = 0;
                  try {
                    let formula = ind.formula || '';
                    Object.entries(initialVariables).forEach(([name, val]) => {
                      const escapedName = name.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
                      formula = formula.replace(new RegExp(escapedName, 'g'), (val as number).toString());
                    });
                    const cleanFormula = formula.replace(/[^-()\d/*+.]/g, '');
                    initialCalculated = eval(cleanFormula) || 0;
                  } catch (e) {
                    initialCalculated = 0;
                  }

                  return {
                    indicator_name: ind.name,
                    formula: ind.formula,
                    unit: ind.unit,
                    variables: initialVariables,
                    calculated_value: initialCalculated,
                    level: '',
                    qualification: '',
                    score: 0,
                    parameters: ind.parameters,
                    conditional_goals: ind.conditional_goals,
                    fixed_goal: ind.fixed_goal
                  };
                }) || []
              }));

              setEvaluation({
                user_id: selectedUserId,
                month: selectedMonth,
                year: selectedYear,
                status: 'borrador',
                total_score: 0,
                general_analysis: '',
                results: initialResults
              });
            } else {
              setEvaluation(null);
            }
          }
        } catch (error) {
          console.error('Error loading evaluation data:', error);
          setEvaluation(null);
        } finally {
          setIsDataLoading(false);
        }
      };
      loadData();
    }
  }, [selectedUserId, selectedMonth, selectedYear]);

  const handleUpdateRealValue = (idx: number, val: string) => {
    if (!evaluation || !evaluation.results) return;

    let numVal = val === '' ? null : Number(val);

    if (numVal !== null && numVal > 100) {
      numVal = 100;
      showNotification('El valor real no puede superar el 100%', 'warning');
    }

    const updatedResults = [...evaluation.results];
    const item = updatedResults[idx];

    item.real_value = numVal;
    item.score = numVal !== null ? calculateScore(numVal, item.kpi_target, !!item.lower_is_better) : 0;

    const totalScore = calculateTotalScore(updatedResults.filter(r => r.real_value !== null));

    setEvaluation({
      ...evaluation,
      results: updatedResults,
      total_score: totalScore
    });
  };

  const handleUpdateIndicatorVariable = (kpiIdx: number, indIdx: number, varName: string, value: string) => {
    if (!evaluation || !evaluation.results) return;

    const updatedResults = [...evaluation.results];
    const kpiRes = updatedResults[kpiIdx];
    if (!kpiRes.indicator_results) return;

    const indRes = kpiRes.indicator_results[indIdx];
    const numVal = value === '' ? 0 : Number(value);

    indRes.variables = { ...indRes.variables, [varName]: numVal };

    // Intentar calcular valor basado en la fórmula
    // Ejemplo simple: (Ventas / Meta) * 100
    // Reemplazamos nombres de variables en la fórmula
    let formula = indRes.formula || '';
    Object.entries(indRes.variables).forEach(([name, val]) => {
      // Escapar caracteres especiales para regex
      const escapedName = name.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
      formula = formula.replace(new RegExp(escapedName, 'g'), val.toString());
    });

    try {
      // Usar un evaluador seguro o simple
      // Nota: eval() es peligroso, en prod usar un parser de expresiones
      // Para este demo usaremos una aproximación simple
      const cleanFormula = formula.replace(/[^-()\d/*+.]/g, '');
      const calculated = eval(cleanFormula);
      indRes.calculated_value = isNaN(calculated) ? 0 : calculated;

      // Determinar nivel y calificación
      const goals = (indRes as any).conditional_goals || [];
      const matchedGoal = goals.find((g: any) => indRes.calculated_value >= g.min_value && indRes.calculated_value <= g.max_value);

      if (matchedGoal) {
        indRes.level = matchedGoal.level;
        indRes.qualification = matchedGoal.qualification;
        // Si el nivel tiene un score definido en la plantilla, usarlo. 
        // Si no, usar el valor calculado (ej: para el caso de 140% -> 100%)
        indRes.score = (matchedGoal.score !== undefined) ? matchedGoal.score : (indRes.calculated_value > 100 ? 100 : indRes.calculated_value);
      }
    } catch (e) {
      console.error('Error al calcular fórmula', e);
    }

    // Recalcular el valor real del KPI como el promedio de los puntajes (scores) de sus indicadores
    if (kpiRes.indicator_results.length > 0) {
      const sumScores = kpiRes.indicator_results.reduce((acc, curr) => acc + (curr.score || 0), 0);
      const avgScore = sumScores / kpiRes.indicator_results.length;

      // El valor real para el KPI es el promedio de los puntajes alcanzados en sus indicadores
      kpiRes.real_value = avgScore;
      // El score del KPI en este caso es el promedio directo de los indicadores (ya que cada indicador tiene su propia meta)
      kpiRes.score = avgScore;
    }

    const totalScore = calculateTotalScore(updatedResults.filter(r => r.real_value !== null));

    setEvaluation({
      ...evaluation,
      results: updatedResults,
      total_score: totalScore
    });
  };

  const handleGenerateSubIndicatorAI = async (kpiIdx: number, indIdx: number) => {
    if (!evaluation || !evaluation.results) return;

    const kpiRes = evaluation.results[kpiIdx];
    if (!kpiRes.indicator_results) return;
    const indRes = kpiRes.indicator_results[indIdx];

    setIsGeneratingAI(true);
    const apiKey = import.meta.env.VITE_GEMINI_API_KEY;
    const url = `https://generativelanguage.googleapis.com/v1beta/models/gemini-flash-latest:generateContent?key=${apiKey}`;

    try {
      const prompt = `
        Razona y analiza como gerente de proyectos con 30 años de experiencia en proyectos inmobiliarios.
        Tu objetivo es generar un análisis técnico y profesional basado en los resultados de desempeño.
        
        EJEMPLO DE ESTILO DESEADO:
        "El KPI de cumplimiento de la meta global de ventas refleja un desempeño excelente (🟢), alcanzando un 140% de cumplimiento con 14 cierres frente a una meta ajustada de 10 unidades. Este resultado evidencia una alta efectividad comercial y capacidad de cierre del equipo, incluso en un contexto retador..."

        INSTRUCCIONES:
        1. Comienza mencionando el indicador y el nivel de desempeño con un emoji (🟢 Excelente/Óptimo, 🟡 Aceptable/Bueno, 🔴 Crítico/Bajo).
        2. Menciona el porcentaje alcanzado y los valores reales vs meta.
        3. Integra el contexto del desempeño de forma narrativa y profesional.
        4. Si el resultado es muy bajo, propón un plan de acción ejecutivo al final.
        5. Mantén un tono ejecutivo, objetivo y motivador.

        DATOS DEL INDICADOR:
        - Colaborador: ${selectedUser?.name}
        - Indicador: ${indRes.indicator_name}
        - Resultado Actual: ${indRes.calculated_value.toFixed(2)}${indRes.unit || '%'}
        - Meta: ${indRes.fixed_goal || 'Según parámetros'}
        - Nivel: ${indRes.level}
        - Calificación: ${indRes.qualification}
        
        Responde directamente en español en un párrafo fluido.
      `;

      const response = await fetch(url, {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({
          contents: [{ parts: [{ text: prompt }] }]
        })
      });

      const result = await response.json();
      const aiText = result.candidates?.[0]?.content?.parts?.[0]?.text;

      if (aiText) {
        const updatedResults = [...evaluation.results];
        updatedResults[kpiIdx].indicator_results![indIdx].ai_analysis = aiText.trim();
        setEvaluation({ ...evaluation, results: updatedResults });
        showNotification("Análisis del indicador generado", "success");
      }
    } catch (error) {
      console.error("AI Error:", error);
      showNotification("Error al generar análisis", "error");
    } finally {
      setIsGeneratingAI(false);
    }
  };

  const handleUpdateDetailTable = (idx: number, tableData: any) => {
    if (!evaluation || !evaluation.results) return;

    const updatedResults = [...evaluation.results];
    const item = updatedResults[idx];
    item.tablaDetalle = tableData;

    if (tableData && tableData.headers && tableData.rows) {
      const valorColIdx = tableData.headers.findIndex((h: string) => h.toLowerCase() === 'valor');

      if (valorColIdx !== -1) {
        const total = tableData.rows.reduce((sum: number, row: string[]) => {
          const val = parseFloat(row[valorColIdx]);
          return sum + (isNaN(val) ? 0 : val);
        }, 0);

        const cappedTotal = Math.min(total, 100);
        item.real_value = cappedTotal;
        item.score = calculateScore(cappedTotal, item.kpi_target, !!item.lower_is_better);
      }
    }

    const totalScore = calculateTotalScore(updatedResults.filter(r => r.real_value !== null));

    setEvaluation({
      ...evaluation,
      results: updatedResults,
      total_score: totalScore
    });
  };

  const handleUpdateAnalysis = (value: string) => {
    setEvaluation(prev => {
      if (!prev) return null;
      return { ...prev, general_analysis: value };
    });
  };

  const handleGenerateAIAnalysis = async () => {
    if (!evaluation || !evaluation.results) return;

    const apiKey = import.meta.env.VITE_GEMINI_API_KEY;
    const url = `https://generativelanguage.googleapis.com/v1beta/models/gemini-flash-latest:generateContent?key=${apiKey}`;

    setIsGeneratingAI(true);

    try {
      const dataStr = evaluation.results
        .filter(r => r.real_value !== null)
        .map(r => `- ${r.kpi_name}: ${Number(r.real_value).toFixed(1)}% (Peso: ${r.kpi_weight}%)`)
        .join("\n");

      const prompt = `
        Razona y analiza como gerente de proyectos con 30 años de experiencia en proyectos inmobiliarios de alto nivel.
        Genera el análisis general de la evaluación mensual del colaborador ${selectedUser?.name}.
        
        ESTILO REQUERIDO:
        Un párrafo fluido, ejecutivo y profesional. Usa emojis (🟢, 🟡, 🔴) para resaltar estados. Enfócate en la efectividad comercial, la adaptación al contexto del mercado y el cumplimiento estratégico.
        
        EJEMPLO:
        "El desempeño general del periodo refleja un compromiso sólido con los objetivos estratégicos (🟢). Con un cumplimiento global del ${evaluation.total_score}%, se observa una ejecución impecable en los KPIs críticos..."

        DATOS DE LA EVALUACIÓN:
        - Puntaje Total: ${Number(evaluation.total_score).toFixed(1)}%
        - Desglose de KPIs:
        ${dataStr}
        
        Responde directamente en español con un tono motivador y de alta gerencia.
      `;

      const response = await fetch(url, {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({
          contents: [{ parts: [{ text: prompt }] }]
        })
      });

      const result = await response.json();
      const aiText = result.candidates?.[0]?.content?.parts?.[0]?.text;

      if (aiText) {
        handleUpdateAnalysis(aiText.trim());
        showNotification("Análisis generado exitosamente por IA", "success");
      }
    } catch (error) {
      console.error("Gemini AI Error:", error);
      showNotification("Error al conectar con la IA", "error");
    } finally {
      setIsGeneratingAI(false);
    }
  };

  const handleSave = async (status: 'borrador' | 'finalizada') => {
    if (!evaluation || !selectedUserId) return;
    setIsSaving(true);
    try {
      // Mapear tablaDetalle de vuelta a 'details' por si el backend usa ese nombre
      const dataToSave = {
        ...evaluation,
        status,
        results: evaluation.results?.map(r => ({
          ...r,
          details: {
            tablaDetalle: r.tablaDetalle,
            indicator_results: r.indicator_results,
            ai_analysis: r.ai_analysis
          }
        }))
      };

      await evaluationService.saveEvaluation(selectedUserId, dataToSave);
      showNotification(
        status === 'finalizada' ? 'Evaluación finalizada con éxito' : 'Borrador guardado correctamente',
        'success'
      );
    } catch (error) {
      showNotification('Error al intentar guardar la evaluación', 'error');
    } finally {
      setIsSaving(false);
    }
  };

  const getScoreColor = (score: number) => {
    if (score >= 90) return 'text-green-600 bg-green-50 border-green-200';
    if (score >= 70) return 'text-yellow-600 bg-yellow-50 border-yellow-200';
    return 'text-red-600 bg-red-50 border-red-200';
  };

  return (
    <div className="max-w-6xl mx-auto space-y-6 md:space-y-8 pb-32 pt-4 md:pt-8">
      {/* Header & Selectors */}
      <div className="p-4 md:p-8 space-y-8">
        <div className="flex flex-col md:flex-row md:items-center justify-between gap-6">
          <div className="flex items-center gap-4">
            <div className="h-12 w-12 bg-[#004C6C] rounded-[20px] flex items-center justify-center text-white shadow-lg shadow-blue-900/10">
              <FileCheck size={24} />
            </div>
            <div>
              <h1 className="text-2xl font-black text-[#004C6C] tracking-tight">Evaluación de Desempeño</h1>
              <p className="text-[11px] text-slate-400 font-bold uppercase tracking-widest">Gestión de resultados mensuales</p>
            </div>
          </div>

          <div className="bg-white px-5 py-3 rounded-2xl border border-slate-100 flex items-center gap-6 shadow-sm">
            <div className="text-right">
              <p className="text-[9px] font-black text-slate-300 uppercase tracking-[0.2em] mb-1">Calificación Total</p>
              <p className={`text-2xl font-black ${evaluation ? getScoreColor(Number(evaluation.total_score || 0)).split(' ')[0] : 'text-slate-300'}`}>
                {Number(evaluation?.total_score || 0).toFixed(1)}%
              </p>
            </div>
          </div>
        </div>

        <div className="bg-white p-6 rounded-[28px] shadow-sm border border-slate-200/60 grid grid-cols-1 md:grid-cols-4 gap-6 pt-6 ">
          <div className="md:col-span-1">
            <Autocomplete
              label="Persona"
              options={users.map(u => ({ value: u.id, label: u.name, sublabel: typeof u.area === 'object' ? u.area?.name : u.area }))}
              value={selectedUserId}
              onChange={setSelectedUserId}
              placeholder="Buscar colaborador..."
            />
          </div>
          <div className="space-y-2">
            <label className="text-[11px] font-bold text-slate-500 uppercase tracking-widest ml-1">Área</label>
            <div className="w-full bg-slate-100/50 border border-slate-100 rounded-2xl px-5 py-4 text-sm font-bold text-slate-400">
              {typeof selectedUser?.area === 'object' ? selectedUser?.area?.name : (selectedUser?.area || 'Sin selección')}
            </div>
          </div>
          <div className="md:col-span-1">
            <CustomSelect
              label="Mes"
              options={meses}
              value={selectedMonth}
              onChange={setSelectedMonth}
            />
          </div>
          <div className="md:col-span-1">
            <CustomSelect
              label="Año"
              options={[2024, 2025, 2026].map(y => ({ value: y, label: y.toString() }))}
              value={selectedYear}
              onChange={setSelectedYear}
            />
          </div>
        </div>
      </div>

      {/* Main Form */}
      {isDataLoading ? (
        <div className="space-y-6">
          <div className="grid grid-cols-1 gap-6">
            {[1, 2, 3].map((i) => (
              <div key={i} className="bg-white p-8 rounded-[32px] border border-slate-100 space-y-6">
                <div className="flex justify-between items-start">
                  <div className="space-y-2 w-1/3">
                    <Skeleton className="h-4 w-20" />
                    <Skeleton className="h-6 w-full" />
                  </div>
                  <Skeleton className="h-8 w-24 rounded-full" />
                </div>
                <div className="flex gap-10">
                  <Skeleton className="h-16 w-80 rounded-2xl" />
                  <Skeleton className="h-16 flex-1 rounded-2xl" />
                </div>
              </div>
            ))}
          </div>
        </div>
      ) : !selectedUserId ? (
        <div className="bg-slate-50 p-20 rounded-2xl border-2 border-dashed border-slate-200 text-center text-slate-400">
          Por favor selecciona un colaborador para comenzar.
        </div>
      ) : !evaluation ? (
        <div className="bg-orange-50 p-12 rounded-2xl border border-orange-200 text-center space-y-4">
          <AlertCircle className="w-12 h-12 text-orange-500 mx-auto" />
          <h3 className="text-lg font-bold text-orange-800">Persona sin plantilla configurada</h3>
          <p className="text-orange-600 max-w-md mx-auto">
            Esta persona aún no tiene KPIs asignados. Ve al módulo de <strong>Plantillas KPI</strong> para configurarlos antes de evaluar.
          </p>
        </div>
      ) : (
        <div className="space-y-6">
          <div className="grid grid-cols-1 gap-6">
            {evaluation.results?.map((res, idx) => (
              <Collapse
                key={idx}
                className="rounded-[32px]! p-0!"
                title={
                  <div className="flex flex-col gap-1">
                    <div className="flex items-center gap-3">
                      <span className="text-xl font-black text-slate-800 tracking-tight">{res.kpi_name}</span>
                      <span className="bg-slate-100 text-slate-500 px-3 py-1 rounded-full text-[10px] font-black uppercase tracking-wider border border-slate-200">
                        Peso: {res.kpi_weight}%
                      </span>
                    </div>
                    <p className="text-xs text-slate-400 font-medium max-w-2xl hidden sm:block">
                      Gestión de resultados y cumplimiento para el área de {res.kpi_name.toLowerCase()}.
                    </p>
                  </div>
                }
                rightElement={
                  <div className={`px-6 py-2 rounded-2xl border flex flex-col items-center justify-center min-w-[100px] ${getScoreColor(res.score)}`}>
                    <span className="text-[8px] font-black uppercase tracking-widest opacity-70">Puntaje</span>
                    <span className="text-xl font-black">
                      {Number(res.score || 0).toFixed(0)}%
                    </span>
                  </div>
                }
              >
                <div className="flex flex-col gap-6 pt-4">
                  <div className="grid grid-cols-1 md:grid-cols-3 items-center gap-8 bg-slate-50/30 p-6 rounded-[24px] border border-slate-100/50">
                    <div className="space-y-3">
                      <div className="flex items-center justify-between mb-1">
                        <label className="text-[11px] font-black text-slate-400 uppercase tracking-widest">Resultado Real ({res.kpi_unit || '%'})</label>
                        {res.tablaDetalle && res.tablaDetalle.headers?.some((h: string) => h.toLowerCase() === 'valor') && (
                          <span className="text-[8px] font-black text-orange-500 flex items-center gap-1 bg-orange-50 px-2 py-0.5 rounded-full">
                            <CheckCircle size={8} /> SYNC
                          </span>
                        )}
                      </div>
                      <div className="relative">
                        <input
                          type="number"
                          value={res.real_value === null ? '' : Number(res.real_value).toFixed(2)}
                          onChange={e => handleUpdateRealValue(idx, e.target.value)}
                          className="w-full bg-white border-2 border-slate-100 rounded-[20px] px-6 py-4 text-3xl font-black text-[#004C6C] shadow-sm focus:border-[#004C6C] outline-none transition-all placeholder:text-slate-200"
                          placeholder="0.00"
                          disabled={res.indicator_results && res.indicator_results.length > 0}
                        />
                        {res.indicator_results && res.indicator_results.length > 0 && (
                          <p className="text-[9px] text-slate-400 mt-2 font-medium italic">* Calculado automáticamente de indicadores</p>
                        )}
                      </div>
                    </div>

                    <div className="flex flex-col items-center md:items-start space-y-2 border-l border-slate-100 pl-8">
                      <p className="text-[11px] font-black text-slate-300 uppercase tracking-[0.2em]">Meta establecida</p>
                      <p className="text-2xl font-black text-slate-700">
                        {res.kpi_target} <span className="text-slate-400 font-bold text-sm uppercase">{res.kpi_unit}</span>
                      </p>
                    </div>

                    <div className="flex flex-col items-center md:items-end space-y-2 border-l border-slate-100 pl-8">
                      <p className="text-[11px] font-black text-slate-300 uppercase tracking-widest">Contribución al Total</p>
                      <div className="bg-[#004C6C]/5 px-4 py-2 rounded-xl border border-[#004C6C]/10">
                        <p className="text-lg font-black text-[#004C6C]">{(res.score * (res.kpi_weight / 100)).toFixed(2)}%</p>
                      </div>
                    </div>
                  </div>

                  {/* Sub-indicadores UI */}
                  {res.indicator_results && res.indicator_results.length > 0 && (
                    <div className="mt-4 p-6 bg-slate-50/50 rounded-3xl border border-slate-100 space-y-6">
                      <div className="flex items-center justify-between">
                        <h4 className="text-xs font-black text-slate-500 uppercase tracking-widest flex items-center gap-2">
                          <List size={14} /> Indicadores Específicos
                        </h4>
                      </div>

                      <div className="grid grid-cols-1 gap-4">
                        {res.indicator_results.map((ind, iIdx) => (
                          <Collapse
                            key={iIdx}
                            title={ind.indicator_name}
                            subtitle={`Fórmula: ${ind.formula}`}
                            rightElement={
                              <div className="text-right">
                                <p className={`text-xs font-black uppercase tracking-wider ${ind.level === 'Excelente' || ind.level === 'Óptimo' ? 'text-green-500' :
                                  ind.level === 'Bueno' ? 'text-yellow-500' :
                                    ind.level === 'Aceptable' ? 'text-blue-500' :
                                      'text-red-500'
                                  }`}>
                                  {ind.level || 'Pendiente'}
                                </p>
                                <p className="text-[9px] text-slate-400 font-bold">{ind.qualification}</p>
                              </div>
                            }
                          >
                            <div className="grid grid-cols-2 sm:grid-cols-4 gap-6">
                              {(ind as any).parameters?.map((param: any, pIdx: number) => (
                                <div key={pIdx} className="space-y-2">
                                  <label className="text-[10px] font-black text-slate-400 uppercase tracking-widest">{param.name}</label>
                                  <input
                                    type="number"
                                    value={ind.variables[param.name] || ''}
                                    onChange={e => handleUpdateIndicatorVariable(idx, iIdx, param.name, e.target.value)}
                                    className="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-3 text-sm font-bold text-slate-700 focus:border-[#004C6C] focus:bg-white outline-none transition-all shadow-sm"
                                    placeholder="0"
                                  />
                                </div>
                              ))}
                              <div className="space-y-2 flex flex-col justify-end">
                                <label className="text-[10px] font-black text-[#004C6C] uppercase tracking-widest">Resultado</label>
                                <div className="w-full bg-[#004C6C]/5 border border-[#004C6C]/10 rounded-2xl px-6 py-4 text-sm font-black text-[#004C6C] shadow-sm flex items-center justify-center min-h-[52px]">
                                  {ind.unit === '$' ? '$ ' : ''}{ind.calculated_value.toLocaleString(undefined, { minimumFractionDigits: 0, maximumFractionDigits: 2 })}{ind.unit !== '$' ? (ind.unit || '%') : ''}
                                </div>
                              </div>
                            </div>

                            <div className="mt-6 space-y-4">
                              <div className="flex items-center justify-between">
                                <label className="text-[10px] font-black text-slate-400 uppercase tracking-widest ml-1">Análisis de Desempeño (IA)</label>
                                <button
                                  type="button"
                                  onClick={() => handleGenerateSubIndicatorAI(idx, iIdx)}
                                  disabled={isGeneratingAI || !ind.calculated_value}
                                  className="flex items-center gap-2 px-3 py-1 bg-blue-50 text-blue-600 rounded-lg text-[9px] font-black uppercase tracking-widest hover:bg-blue-100 transition-all disabled:opacity-50"
                                >
                                  <Sparkles size={10} /> {isGeneratingAI ? 'Procesando...' : 'Analizar con IA'}
                                </button>
                              </div>
                              <div className="relative">
                                <div className="w-full bg-slate-100/50 border border-slate-100 rounded-2xl px-6 py-4 text-xs font-medium text-slate-500 italic outline-none min-h-[100px] leading-relaxed transition-all prose prose-sm max-w-none">
                                  {ind.ai_analysis ? (
                                    <ReactMarkdown>{ind.ai_analysis}</ReactMarkdown>
                                  ) : (
                                    <p className="text-slate-300">Haz clic en 'Analizar con IA' para generar una interpretación automática...</p>
                                  )}
                                </div>
                              </div>
                            </div>
                          </Collapse>
                        ))}
                      </div>

                      {res.ai_analysis && (
                        <div className="p-4 bg-white rounded-2xl border border-blue-100 shadow-sm">
                          <div className="flex items-center gap-2 mb-2">
                            <Sparkles size={12} className="text-blue-500" />
                            <span className="text-[10px] font-black text-blue-500 uppercase tracking-widest">Análisis IA del Indicador</span>
                          </div>
                          <p className="text-xs text-slate-600 italic leading-relaxed">"{res.ai_analysis}"</p>
                        </div>
                      )}
                    </div>
                  )}

                  <div className="pt-2 border-t border-slate-50">
                    <KPIDetailTable
                      data={res.tablaDetalle}
                      onChange={(data) => handleUpdateDetailTable(idx, data)}
                    />
                  </div>
                </div>
              </Collapse>
            ))}
          </div>

          <div className="bg-white p-8 rounded-[28px] border border-slate-200 shadow-sm space-y-6">
            <div className="flex items-center justify-between">
              <div className="flex items-center gap-3">
                <div className="p-2 bg-[#004C6C] rounded-xl text-white">
                  <Info size={20} />
                </div>
                <h3 className="text-xl font-black text-[#004C6C] tracking-tight">Análisis General</h3>
              </div>

              <button
                onClick={handleGenerateAIAnalysis}
                disabled={isGeneratingAI || !evaluation?.results?.some(r => r.real_value !== null)}
                className="flex items-center gap-2 px-5 py-2 bg-linear-to-r from-blue-600 to-indigo-600 text-white rounded-xl font-bold text-xs uppercase tracking-widest hover:shadow-lg transition-all disabled:opacity-50 disabled:cursor-not-allowed group"
              >
                {isGeneratingAI ? (
                  <Loader2 className="w-4 h-4 animate-spin" />
                ) : (
                  <Sparkles className="w-4 h-4 group-hover:animate-pulse" />
                )}
                {isGeneratingAI ? "Analizando..." : "Generar con IA"}
              </button>
            </div>

            <div className="relative group">
              <textarea
                className="w-full h-40 p-6 bg-slate-50 border border-slate-100 rounded-2xl text-slate-600 text-sm font-medium focus:bg-white focus:border-[#004C6C] outline-none transition-all placeholder:text-slate-300 leading-relaxed"
                placeholder="Escribe un análisis cualitativo sobre el desempeño general del colaborador en este mes..."
                value={evaluation.general_analysis || ''}
                onChange={(e) => handleUpdateAnalysis(e.target.value)}
              />
              {evaluation.general_analysis && (
                <div className="mt-4 p-6 bg-blue-50/30 border border-blue-100 rounded-2xl prose prose-blue max-w-none text-sm text-slate-600">
                  <p className="text-[10px] font-black text-blue-400 uppercase tracking-widest mb-2">Vista Previa Formateada</p>
                  <ReactMarkdown>{evaluation.general_analysis}</ReactMarkdown>
                </div>
              )}
            </div>
            <p className="text-[10px] text-slate-400 font-bold uppercase tracking-widest italic">
              * El análisis cualitativo es fundamental para el feedback de fin de mes.
            </p>
          </div>

          <div className="flex justify-end gap-6 pt-4">
            <button
              onClick={() => handleSave('borrador')}
              disabled={isSaving}
              className="px-10 py-5 bg-white border-2 border-slate-100 text-slate-500 rounded-[20px] font-extrabold hover:bg-slate-50 hover:text-slate-700 hover:border-slate-200 transition-all flex items-center gap-3 tracking-tight disabled:opacity-50"
            >
              <Save size={22} /> {isSaving ? 'GUARDANDO...' : 'GUARDAR BORRADOR'}
            </button>
            <button
              onClick={() => setIsFinalizeModalOpen(true)}
              disabled={isSaving}
              className="px-10 py-5 bg-[#004C6C] text-white rounded-[20px] font-extrabold hover:bg-[#003a53] shadow-2xl shadow-blue-900/30 hover:scale-[1.02] active:scale-[0.98] transition-all flex items-center gap-3 tracking-tight disabled:opacity-50"
            >
              <FileCheck size={22} /> FINALIZAR EVALUACIÓN
            </button>
          </div>

          <Modal
            isOpen={isFinalizeModalOpen}
            onClose={() => setIsFinalizeModalOpen(false)}
            onConfirm={() => handleSave('finalizada')}
            title="¿Finalizar evaluación?"
            message="Una vez finalizada, la evaluación no podrá ser modificada. Asegúrate de que todos los valores reales y el análisis general sean correctos."
            confirmText="Sí, finalizar"
            cancelText="Revisar todavía"
            type="warning"
          />
        </div>
      )}
    </div>
  );
}
