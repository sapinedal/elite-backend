# Arquitectura Frontend - Proyecto Elite

Esta guía define la estructura de archivos y principios arquitectónicos para el frontend del proyecto **Elite**, diseñada para ser **robusta, ágil y escalable**.

## 1. Principios Core

- **Modularidad (Feature-Based):** El código se organiza por "características" o "módulos" en lugar de solo por tipo de archivo. Esto evita tener carpetas gigantescas y facilita que varios desarrolladores trabajen en paralelo.
- **Separación de Preocupaciones (SoC):** La lógica de negocio (`hooks`, `services`) debe estar separada de la representación visual (`components`).
- **Single Source of Truth:** Una única fuente de verdad para el estado, ya sea global (Zustand/Context) o local.
- **Tipado Estricto:** Uso mandatorio de TypeScript para prevenir errores en tiempo de desarrollo.

## 2. Estructura de Directorios Propuesta

```text
src/
├── api/              # Clientes de API (Axios instances, interceptors)
├── assets/           # Imágenes, fuentes, svgs globales
├── components/       # Componentes transversales
│   ├── ui/           # Componentes base/átomos (Botones, Inputs - Shadcn)
│   ├── shared/       # Componentes de negocio reutilizables (Tablas, Modales)
│   └── layouts/      # Envoltorios de página (MainLayout, AuthLayout)
├── config/           # Variables de entorno y constantes globales
├── context/          # Context API de React para estados globales simples
├── hooks/            # Hooks personalizados globales
├── lib/              # Configuraciones de librerías externas (i18n, queryClient)
├── modules/          # Lógica de negocio dividida por dominios (CARACTERÍSTICA)
│   ├── auth/         # Ejemplo: Módulo de Autenticación
│   │   ├── components/ # Componentes exclusivos del módulo
│   │   ├── hooks/      # Lógica de estado/side-effects del módulo
│   │   ├── services/   # Llamadas a API específicas de auth
│   │   ├── types/      # Interfaces y tipos del módulo
│   │   └── pages/      # Vistas de este módulo
│   └── users/        # Ejemplo: Módulo de Usuarios
├── pages/            # Punto de entrada de rutas (a veces solo exportan de modules)
├── store/            # Gestión de estado global (Zustand)
├── types/            # Tipos de TypeScript globales
└── utils/            # Funciones puras de utilidad (formatters, validators)
```

## 3. Desglose por Capas

### 📂 modules/ (El corazón de la app)
Cada carpeta dentro de `modules` representa un dominio de negocio. 
- **Regla de Oro:** Si un componente solo se usa en "Usuarios", va en `modules/users/components`. Si se usa en más de dos módulos, se sube a `src/components`.

### 📂 api/ & services/
Utilizamos un patrón de servicios para las peticiones HTTP.
- Centralizar configuraciones de Axios en `src/api/client.ts`.
- Definir las peticiones en archivos específicos del módulo o globales.

### 📂 components/ui/
Aquí residen los componentes que no tienen lógica de negocio (puros). Se recomienda seguir un sistema de diseño consistente.

### 📂 hooks/
Dividimos los hooks en:
- **Globales:** `useAuth`, `useTheme`, `useDebounce`.
- **De Módulo:** `useUserList`, `useProjectForm`.

## 4. Flujo de Trabajo (Best Practices)

1. **Definición de Tipos:** Antes de programar lógica, define las interfaces en `types.ts`.
2. **Servicios:** Crea las funciones de llamada a API en `services/`.
3. **Lógica de Estado (Hooks):** Encapsula llamadas a API y manejo de estados en un hook.
4. **UI:** Conecta el componente al hook.

## 5. Rendimiento y Escalabilidad

- **Lazy Loading:** Las rutas en `App.tsx` o `routes.tsx` deben usar `React.lazy` para dividir el bundle.
- **Validación de Datos:** Uso de `Zod` para validar esquemas de formularios y respuestas de API.
- **Abstracción:** No temas crear abstracciones para componentes repetitivos (ej: `FormBuilder`, `DataTable`).
