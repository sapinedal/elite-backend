# Sistema de Diseño - Proyecto Elite (Inspiración Inverconstrucción)

Para el proyecto **Elite**, adoptaremos una identidad visual basada en el sector de la construcción y la ingeniería, tomando como referencia estética a **Inverconstrucción**. Esto proyecta solidez, profesionalismo y confianza.

## 1. Paleta de Colores (Brand Identity)

Utilizaremos una combinación de azul profundo y naranja vibrante para generar contraste y jerarquía clara.

- **Primario (Naranja Construcción):** `#EE9D4C`
  - Uso: Botones principales (CTA), iconos destacados, acentos de estado y hover.
- **Secundario (Azul Marino Corporativo):** `#004C6C`
  - Uso: Headers, footers, títulos de secciones y elementos estructurales.
- **Neutros:**
  - *Fondo:* `#F2F2F2` (Gris muy claro) para secciones alternas y `#FFFFFF` para limpieza visual.
  - *Texto:* `#333333` para legibilidad máxima en cuerpo y `#004C6C` para énfasis.

## 2. Tipografía

Buscamos una lectura técnica y moderna.
- **Fuentes:** **Montserrat** (para títulos) y **Open Sans** o **Roboto** (para cuerpo de texto).
- **Pesos:**
  - Títulos: Bold (700) o Extra Bold (800).
  - Cuerpo: Regular (400) o Medium (500).

## 3. Estética y Componentes (UI)

- **Layout:** Basado en grilla (Grid), con espaciado amplio para evitar la saturación de información. Estilo "Industrial Limpio".
- **Botones:** Rectangulares con bordes ligeramente redondeados (`rounded-md` / 6px). Efecto de elevación sutil al hacer hover.
- **Cards:** Uso de sombras suaves (`box-shadow: 0 4px 6px -1px rgb(0 0 0 / 0.1)`) para dar profundidad sin usar bordes pesados.
- **Iconografía:** Iconos lineales o circulares en los colores de marca para representar estadísticas o procesos.
- **Imágenes:** Uso de fotografías de alta calidad con overlays oscuros cuando se coloque texto encima para garantizar el contraste.

## 4. Estándares Visuales para Elite

- **Modo Claro por Defecto:** Al ser una herramienta corporativa/industrial, el fondo blanco con acentos grises claros mejora la concentración.
- **Feedback:** Los estados de carga (skeletons) seguirán la estructura de las cards del dashboard.
- **Micro-interacciones:** Transiciones fluidas en cambios de estado de botones y navegación (0.2s ease-in-out).

---
> [!NOTE]
> Este manual debe ser la referencia para la personalización de los componentes de **Shadcn/UI** en la carpeta `src/components/ui`.
