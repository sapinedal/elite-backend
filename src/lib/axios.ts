import axios, { AxiosError } from 'axios';
import { config } from '../config/env';

const api = axios.create({
  baseURL: config.API_URL,
  headers: {
    'Content-Type': 'application/json',
  },
  timeout: 100000,
});

// Interceptor para agregar el token automáticamente
api.interceptors.request.use(
  (config) => {
    const token = localStorage.getItem('token');
    if (token) {
      config.headers.Authorization = `Bearer ${token}`;
    }
    return config;
  },
  (error) => {
    return Promise.reject(error);
  }
);

// Interceptor para manejar errores de respuesta
api.interceptors.response.use(
  (response) => {
    return response;
  },
  (error: AxiosError<any>) => {
    if (error.response?.status === 401) {
      const token = localStorage.getItem('token');
      if (token) {
        localStorage.removeItem('token');
        localStorage.removeItem('user');
        window.location.href = '/';
      }
    } else if (error.response?.status === 422) {
      // Mapear errores de validación del FormRequest
      // Soportar dos formatos: { errors: {...} } y objeto plano {...}
      const data = error.response?.data as any;
      const candidate = (data && typeof data === 'object') ? (data.errors ?? data) : {};
      const mensajes: string[] = [];

      if (candidate && typeof candidate === 'object') {
        Object.entries(candidate).forEach(([_, messages]) => {
          if (Array.isArray(messages)) {
            mensajes.push(...messages);
          } else if (typeof messages === 'string') {
            mensajes.push(messages);
          }
        });
      }

      const mensaje = mensajes.length > 0
        ? mensajes.slice(0, 3).join('\n')
        : 'Error de validación';

      console.log(mensaje);
    } else if (error.response?.status === 500) {
      const mensaje = error.response?.data?.error ||
        error.response?.data?.message ||
        'Error interno del servidor';
      console.log(mensaje);
    } else if (error.response?.status === 400) {
      const mensaje = error.response?.data?.error ||
        error.response?.data?.message ||
        'Solicitud inválida';
      console.log(mensaje);
    }

    return Promise.reject(error);
  }
);

export default api;