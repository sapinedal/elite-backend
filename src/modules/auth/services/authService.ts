import api from '../../../lib/axios';

export const authService = {
  login: async (credentials: any) => {
    const { data } = await api.post('/v1/auth/login', credentials);
    return data;
  },
  logout: async () => {
    const { data } = await api.post('/v1/auth/logout');
    return data;
  }
};
