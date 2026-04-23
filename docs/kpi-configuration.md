# Configuración de KPIs e Indicadores

Este documento explica cómo configurar y agregar nuevos KPIs e indicadores al sistema Elite. El sistema utiliza una estructura jerárquica donde un KPI puede contener múltiples indicadores con fórmulas dinámicas.

## 1. Estructura de un KPI

Un KPI (Key Performance Indicator) es la unidad de medida principal vinculada a una etapa del proceso (ej. "Estrategia y Planificación").

### Atributos principales:
- **name**: Nombre descriptivo.
- **weight**: Peso porcentual (ej. 30) que aporta a la evaluación total.
- **target**: Meta global (generalmente 100%).
- **stage**: Categoría a la que pertenece (A, B, C, D).
- **indicators**: Array de objetos que definen las métricas específicas.

## 2. Configuración de Indicadores

Los indicadores permiten cálculos automáticos basados en fórmulas.

### Ejemplo de configuración en el Seeder:

```php
[
    'name' => 'Costo por Lead (CPL) Promedio',
    'definition' => 'Evalúa la eficiencia de las campañas digitales.',
    'formula' => 'Costo_Total / Numero_Leads',
    'unit' => '$',
    'parameters' => [
        ['name' => 'Costo_Total', 'value' => 0],
        ['name' => 'Numero_Leads', 'value' => 1],
    ],
    'conditional_goals' => [
        ['level' => 'Óptimo', 'min_value' => 0, 'max_value' => 3000, 'color' => 'optimal', 'score' => 100],
        ['level' => 'Deficiente', 'min_value' => 3001, 'max_value' => 99999, 'color' => 'deficient', 'score' => 0],
    ],
]
```

### Componentes del Indicador:

#### A. Fórmulas y Parámetros
- **formula**: Cadena de texto con la operación matemática. Las variables deben coincidir exactamente con los nombres en `parameters`.
- **parameters**: Lista de variables que el usuario deberá llenar. El `value` inicial sirve como predeterminado.
- **unit**: El símbolo que se mostrará junto al resultado (`$`, `%`, etc.).

#### B. Metas Condicionales (`conditional_goals`)
Define cómo se califica el resultado según rangos:
- **min_value / max_value**: Rango numérico (inclusive).
- **level**: Nombre del nivel (ej. "Excelente", "Bajo").
- **score**: Puntaje (0-100) que se asignará si el resultado cae en este rango.
- **color**: Define el estilo visual en el frontend:
    - `optimal` / `excellent` -> Verde
    - `acceptable` / `good` -> Amarillo
    - `at_risk` -> Azul/Naranja
    - `deficient` -> Rojo

## 3. Tipos de Indicadores Comunes

### I. Indicador de Porcentaje (Cumplimiento)
Utilizado para medir cuánto se alcanzó de una meta.
- **Fórmula**: `(Real / Meta) * 100`
- **Unit**: `%`

### II. Indicador de Eficiencia (Costo/Ratio)
Utilizado para medir costos unitarios o calidad.
- **Fórmula**: `Costo / Volumen` o `Correctos / Totales`
- **Unit**: `$` o `%`

### III. Indicador de Comparación (Crecimiento)
Para comparar periodos.
- **Fórmula**: `((Mes_Actual - Mes_Anterior) / Mes_Anterior) * 100`

## 4. Proceso para agregar nuevos KPIs

1.  **Modificar el Seeder**: Editar `database/seeders/CommercialAreaSeeder.php`.
2.  **Definir Fórmulas**: Asegurarse de que los nombres de los parámetros no contengan espacios (usar `_`).
3.  **Ejecutar Seed**:
    ```bash
    php artisan db:seed --class=CommercialAreaSeeder
    ```
4.  **Verificación**: La aplicación frontend cargará automáticamente los nuevos campos y realizará los cálculos basados en las metas configuradas.
