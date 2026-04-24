import { format, formatDistanceToNow } from "date-fns";
import { es } from "date-fns/locale";

/**
 * Formatea fecha/hora en formato Colombiano
 * Ej: 19/09/2025 14:07
 */
export function formatDateTime(dateString: string): string {
  if (!dateString) return "-";
  const date = new Date(dateString);
  return format(date, "dd/MM/yyyy HH:mm", { locale: es });
}

/**
 * Solo fecha
 * Ej: 19/09/2025
 */
export function formatDate(dateString: string): string {
  if (!dateString) return "-";
  const date = new Date(dateString);
  return format(date, "dd/MM/yyyy", { locale: es });
}

/**
 * Obtiene solo la parte YYYY-MM-DD para inputs
 */
export function toYmd(dateString: string | null | undefined): string {
  if (!dateString) return "";
  // Si ya viene en formato ISO o similar, extraemos la parte de la fecha
  if (dateString.includes('T')) {
    return dateString.split('T')[0];
  }
  // Si viene con espacio (ej: from backend)
  if (dateString.includes(' ')) {
    return dateString.split(' ')[0];
  }
  return dateString;
}

/**
 * Fecha relativa
 * Ej: "hace 5 minutos", "hace 3 días"
 */
export function formatRelativeDate(dateString: string): string {
  if (!dateString) return "-";
  const date = new Date(dateString);
  return formatDistanceToNow(date, { addSuffix: true, locale: es });
}
