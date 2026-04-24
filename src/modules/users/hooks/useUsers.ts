import { useState, useEffect } from 'react';
import type { User, KPI } from '../types';
import { userService } from '../services/userService';

export const useUsers = () => {
  const [users, setUsers] = useState<User[]>([]);
  const [isLoading, setIsLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  const fetchUsers = async () => {
    try {
      setIsLoading(true);
      const data = await userService.getAllUsers();
      setUsers(data);
    } catch (err) {
      setError('Error al cargar usuarios');
    } finally {
      setIsLoading(false);
    }
  };

  useEffect(() => {
    fetchUsers();
  }, []);

  return { users, isLoading, error, refetch: fetchUsers };
};

export const useUserKPIs = (userId: number | null) => {
  const [kpis, setKpis] = useState<KPI[]>([]);
  const [isLoading, setIsLoading] = useState(false);

  useEffect(() => {
    if (userId) {
      const fetchKPIs = async () => {
        setIsLoading(true);
        try {
          const data = await userService.getUserKPIs(userId);
          setKpis(data);
        } catch (err) {
          console.error(err);
        } finally {
          setIsLoading(false);
        }
      };
      fetchKPIs();
    }
  }, [userId]);

  const saveKPIs = async (newKpis: Partial<KPI>[]) => {
    if (!userId) return;
    try {
      const updatedUser = await userService.syncUserKPIs(userId, newKpis);
      setKpis(updatedUser.kpis || []);
      return updatedUser;
    } catch (err) {
      throw err;
    }
  };

  return { kpis, isLoading, saveKPIs };
};
