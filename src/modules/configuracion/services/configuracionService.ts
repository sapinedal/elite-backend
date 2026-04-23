import api from "../../../lib/axios";

export interface Area {
  id: number;
  name: string;
  description: string;
  positions: Position[];
}

export interface Position {
  id: number;
  name: string;
  area_id: number;
}

export const configuracionService = {
  getAreas: async (): Promise<Area[]> => {
    const response = await api.get('/v1/configuracion/areas');
    return response.data;
  },

  createArea: async (data: Partial<Area>): Promise<Area> => {
    const response = await api.post('/v1/configuracion/areas', data);
    return response.data;
  },

  updateArea: async (id: number, data: Partial<Area>): Promise<Area> => {
    const response = await api.put(`/v1/configuracion/areas/${id}`, data);
    return response.data;
  },

  deleteArea: async (id: number): Promise<void> => {
    await api.delete(`/v1/configuracion/areas/${id}`);
  },

  getPositions: async (areaId: number): Promise<Position[]> => {
    const response = await api.get(`/v1/configuracion/areas/${areaId}/positions`);
    return response.data;
  },

  createPosition: async (data: Partial<Position>): Promise<Position> => {
    const response = await api.post('/v1/configuracion/positions', data);
    return response.data;
  },

  updatePosition: async (id: number, data: Partial<Position>): Promise<Position> => {
    const response = await api.put(`/v1/configuracion/positions/${id}`, data);
    return response.data;
  },

  deletePosition: async (id: number): Promise<void> => {
    await api.delete(`/v1/configuracion/positions/${id}`);
  }
};
