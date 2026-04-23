export const calculateScore = (real: number, target: number, lowerIsBetter: boolean = false): number => {
  if (target === 0) return 0;
  
  if (lowerIsBetter) {
    if (real <= target) return 100;
    // Penalización proporcional: si es el doble de la meta, es 0 (ejemplo)
    // Pero una formula común es: max(0, 100 - ((real - target) / target) * 100)
    return Math.max(0, 100 - ((real - target) / target) * 100);
  } else {
    // Si mayor es mejor
    return Math.min(100, (real / target) * 100);
  }
};

export const calculateTotalScore = (results: { score: number, kpi_weight: number }[]): number => {
  // Solo sumamos los pesos de los que tienen valor ingresado? 
  // No, el peso es fijo. Si no se ingresa valor, se cuenta como 0 o se ignora dependiendo de la lógica de negocio.
  // El usuario dijo: "calificacionTotal = Σ(puntajeItem × peso/100) para todos los ítems con valor ingresado. Si los pesos usados son <100, se normaliza."
  
  let totalScore = 0;
  let totalWeightsUsed = 0;

  results.forEach(res => {
    totalScore += (res.score * (res.kpi_weight / 100));
    totalWeightsUsed += res.kpi_weight;
  });

  if (totalWeightsUsed === 0) return 0;
  
  // Normalizar si no se evaluaron todos los KPIs pero queremos un promedio sobre lo evaluado
  return (totalScore / totalWeightsUsed) * 100;
};
