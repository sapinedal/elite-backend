import api from '../../../lib/axios';
import type { Evaluation } from '../types';

export const evaluationService = {
  getEvaluation: async (userId: number, month: number, year: number): Promise<Evaluation | null> => {
    const { data } = await api.get(`/v1/users/${userId}/evaluations`, {
      params: { month, year }
    });
    return data;
  },

  saveEvaluation: async (userId: number, evaluation: Partial<Evaluation>): Promise<Evaluation> => {
    const { data } = await api.post(`/v1/users/${userId}/evaluations`, evaluation);
    return data;
  },

  getHistory: async (userId: number): Promise<Evaluation[]> => {
    const { data } = await api.get(`/v1/users/${userId}/history`);
    return data;
  },

  getAllHistory: async (params?: any): Promise<Evaluation[]> => {
    const { data } = await api.get('/v1/evaluations/history', { params });
    return data;
  }
};

