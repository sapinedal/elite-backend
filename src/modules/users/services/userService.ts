import api from '../../../lib/axios';
import type { User, KPI } from '../types';

export const userService = {
  getAllUsers: async (): Promise<User[]> => {
    const { data } = await api.get('/v1/users');
    return data;
  },

  getUserById: async (id: number): Promise<User> => {
    const { data } = await api.get(`/v1/users/${id}`);
    return data;
  },

  createUser: async (userData: Partial<User>): Promise<User> => {
    const { data } = await api.post('/v1/users', userData);
    return data;
  },

  updateUser: async (id: number, userData: Partial<User>): Promise<User> => {
    const { data } = await api.put(`/v1/users/${id}`, userData);
    return data;
  },

  deleteUser: async (id: number): Promise<void> => {
    await api.delete(`/v1/users/${id}`);
  },

  changePassword: async (id: number, passwordData: any): Promise<void> => {
    await api.post(`/v1/users/${id}/change-password`, passwordData);
  },

  getUserKPIs: async (userId: number): Promise<KPI[]> => {
    const { data } = await api.get(`/v1/users/${userId}/kpis`);
    return data;
  },

  syncUserKPIs: async (userId: number, kpis: Partial<KPI>[]): Promise<User> => {
    const { data } = await api.post(`/v1/users/${userId}/kpis/sync`, { kpis });
    return data;
  }
};
