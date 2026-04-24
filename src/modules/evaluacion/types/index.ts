
export interface Evaluation {
  id?: number;
  user_id: number;
  month: number;
  year: number;
  status: 'borrador' | 'finalizada';
  total_score: number;
  general_analysis: string | null;
  results: EvaluationResult[];
}

export interface EvaluationResult {
  id?: number;
  evaluation_id?: number;
  kpi_id: number | null;
  kpi_name: string;
  kpi_weight: number;
  kpi_target: number;
  kpi_unit?: string;
  lower_is_better?: boolean;
  real_value: number | null;
  score: number;
  tablaDetalle?: any;
  indicator_results?: IndicatorResult[];
  ai_analysis?: string;
}

export interface IndicatorResult {
  indicator_name: string;
  formula: string;
  unit?: string;
  variables: Record<string, number>;
  calculated_value: number;
  level: string;
  qualification: string;
  score: number;
  ai_analysis?: string;
  parameters?: any[];
  conditional_goals?: any[];
  fixed_goal?: number | null;
}

export type Mes = {
  value: number;
  label: string;
};

export const meses: Mes[] = [
  { value: 1, label: 'Enero' },
  { value: 2, label: 'Febrero' },
  { value: 3, label: 'Marzo' },
  { value: 4, label: 'Abril' },
  { value: 5, label: 'Mayo' },
  { value: 6, label: 'Junio' },
  { value: 7, label: 'Julio' },
  { value: 8, label: 'Agosto' },
  { value: 9, label: 'Septiembre' },
  { value: 10, label: 'Octubre' },
  { value: 11, label: 'Noviembre' },
  { value: 12, label: 'Diciembre' },
];
