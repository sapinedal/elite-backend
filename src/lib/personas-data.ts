export interface KPI {
  id: string;
  nombre: string;
  descripcion: string;
  formula: string;
  meta: number;
  unidad: string;
  etapa: string;
  peso: number;
  incidencia: number; // 100 for califica, 0 for info
  menorEsMejor?: boolean;
}

export interface Persona {
  personId: string;
  nombre: string;
  apellido: string;
  documento: string;
  area: string;
  cargo: string;
  kpis?: KPI[];
}

export const personasData: Persona[] = [
  {
    personId: 'sara-moreno',
    nombre: 'SARA ELENA',
    apellido: 'MORENO OROZCO',
    documento: '53081.362',
    area: 'Comercial',
    cargo: 'Directora comercial',
    kpis: [
      {
        id: 'estrategia-planificacion',
        nombre: 'Estrategia y Planificación Comercial',
        descripcion: 'Gestión estratégica del área comercial',
        formula: 'Evaluación de cumplimiento de metas estratégicas',
        meta: 100,
        unidad: '%',
        etapa: 'Estrategia',
        peso: 30,
        incidencia: 100,
      },
      {
        id: 'liderazgo-gestion',
        nombre: 'Liderazgo y Gestión del Equipo Comercial',
        descripcion: 'Gestión y desarrollo del equipo de ventas',
        formula: 'Cumplimiento de objetivos del equipo',
        meta: 100,
        unidad: '%',
        etapa: 'Gestión Humana',
        peso: 30,
        incidencia: 100,
      },
      {
        id: 'gestion-operativa',
        nombre: 'Gestión Operativa y Control de Ventas',
        descripcion: 'Control de procesos operativos de venta',
        formula: 'Eficiencia en cierres y prospección',
        meta: 100,
        unidad: '%',
        etapa: 'Operación',
        peso: 30,
        incidencia: 100,
      },
      {
        id: 'gestion-documental',
        nombre: 'Gestión Documental y Procesos Legales/Internos',
        descripcion: 'Cumplimiento de trámites legales e internos',
        formula: 'Correcto diligenciamiento de promesas y contratos',
        meta: 100,
        unidad: '%',
        etapa: 'Legal',
        peso: 10,
        incidencia: 100,
      }
    ]
  },
  {
    personId: 'ingrid-ospicio',
    nombre: 'INGRID PAOLA',
    apellido: 'OSPICIO PACHECO',
    documento: '15443145',
    area: 'Comercial',
    cargo: 'Líder de sala',
  },
  {
    personId: 'paola-arenas',
    nombre: 'PAOLA ANDREA',
    apellido: 'ARENAS GAVIRIA',
    documento: '16472142',
    area: 'Comercial',
    cargo: 'Asesora comercial',
  },
  {
    personId: 'natalia-posada',
    nombre: 'NATALIA ANDREA',
    apellido: 'POSADA RAVE',
    documento: '32151064',
    area: 'Comercial',
    cargo: 'Asesora comercial',
  }
];
