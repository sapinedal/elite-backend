import React, { createContext, useContext, useState, useEffect } from "react";
import type { ReactNode } from "react";
import api from "../lib/axios";

export interface AuthUser {
  id: number;
  nombre: string;
  first_name?: string;
  last_name?: string;
  document?: string;
  area?: string;
  position?: string;
  email: string;
  permissions: string[];
  roles: string[];
  sede_id?: number | null;
  sede?: string | null;
  password_changed?: boolean;
  tiene_personal_a_cargo?: boolean;
}

interface AuthContextType {
  user: AuthUser | null;
  token: string | null;
  isLoading: boolean;
  isPostLoginLoading: boolean;
  login: (data: { token: string; password_changed?: boolean }) => Promise<void>;
  logout: () => void;
  refreshUser: () => Promise<void>;
  isAuthenticated: boolean;
  useCan: (permission: string) => boolean;
}

const AuthContext = createContext<AuthContextType | undefined>(undefined);

export const useAuth = () => {
  const context = useContext(AuthContext);
  if (context === undefined) {
    throw new Error("useAuth debe ser usado dentro de un AuthProvider");
  }
  return context;
};

interface AuthProviderProps {
  children: ReactNode;
}

export const AuthProvider: React.FC<AuthProviderProps> = ({ children }) => {
  const [user, setUser] = useState<AuthUser | null>(null);
  const [token, setToken] = useState<string | null>(null);
  const [isLoading, setIsLoading] = useState(true);
  const [isPostLoginLoading, setIsPostLoginLoading] = useState(false);

  useEffect(() => {
    const loadUser = async () => {
      const savedToken = localStorage.getItem("token");

      if (!savedToken) {
        setIsLoading(false);
        return;
      }

      try {
        setToken(savedToken);
        api.defaults.headers.common["Authorization"] = `Bearer ${savedToken}`;

        const { data } = await api.get("/v1/users/me");
        setUser(data);
      } catch (error: any) {
        console.error("Error al obtener /auth/me:", error);
        if (error.response?.status === 401 || error.response?.status === 422) {
          logout();
        }
      } finally {
        setIsLoading(false);
      }

    };

    loadUser();
  }, []);

  const login = async (data: { token: string; password_changed?: boolean }) => {
    setIsPostLoginLoading(true);
    try {
      localStorage.setItem("token", data.token);
      setToken(data.token);
      api.defaults.headers.common["Authorization"] = `Bearer ${data.token}`;

      const res = await api.get("/v1/users/me");
      const userData = { 
        ...res.data, 
        password_changed: data.password_changed ?? res.data.password_changed,
        tiene_personal_a_cargo: res.data.tiene_personal_a_cargo 
      };
      
      localStorage.setItem("userName", userData.nombre);
      setUser(userData);
    } catch (error) {
      console.error("Error en login:", error);
      throw error;
    } finally {
      setIsPostLoginLoading(false);
    }
  };

  const logout = () => {
    localStorage.removeItem("token");
    localStorage.removeItem("userName");
    setUser(null);
    setToken(null);
    delete api.defaults.headers.common["Authorization"];
  };

  const useCan = (permission: string): boolean => {
    return user?.permissions?.includes(permission) ?? false;
  };

  const refreshUser = async () => {
    try {
      const { data } = await api.get("/v1/users/me");
      setUser(data);
    } catch (error) {
      console.error("Error al refrescar usuario:", error);
    }
  };

  const value: AuthContextType = {
    user,
    token,
    isLoading,
    isPostLoginLoading,
    login,
    logout,
    refreshUser,
    isAuthenticated: !!token && !!user,
    useCan,
  };

  return <AuthContext.Provider value={value}>{children}</AuthContext.Provider>;
};
