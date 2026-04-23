import { useAuth } from '../context/AuthContext';
import { Menu, LogOut } from 'lucide-react';

interface HeaderProps {
  onMenuClick: () => void;
}

export default function Header({ onMenuClick }: HeaderProps) {
  const { user, logout } = useAuth();

  return (
    <header className="h-[64px] bg-white border-b border-slate-100 sticky top-0 z-20 flex items-center justify-between px-4 md:px-8 shadow-sm">
      {/* Lado Izquierdo: Botón Menú (Solo móvil) */}
      <div className="flex items-center gap-4">
        <button
          onClick={onMenuClick}
          className="p-2 text-slate-500 hover:bg-slate-50 rounded-xl lg:hidden transition-all"
        >
          <Menu size={24} />
        </button>
        <span className="hidden md:block text-[10px] font-black text-slate-300 uppercase tracking-[0.3em]">
          ELITE
        </span>
      </div>

      {/* Lado Derecho: Perfil y Logout */}
      <div className="flex items-center gap-4 md:gap-6">
        <div className="flex items-center gap-3">
          <div className="flex flex-col items-end sm:flex">
            <span className="text-sm font-black text-[#004C6C] tracking-tight leading-none mb-1">
              {user?.nombre || 'Usuario'}
            </span>
            <span className="text-[10px] font-bold text-slate-400 uppercase tracking-widest italic leading-none">
              {user?.roles?.[0] || 'Administrador'}
            </span>
          </div>
        </div>

        <div className="w-px h-6 bg-slate-100 mx-2 hidden sm:block"></div>

        <button
          onClick={logout}
          className="p-2 md:px-4 md:py-2 bg-[#EE9D4C] rounded-xl transition-all flex items-center gap-2 group"
          title="Cerrar sesión"
        >
          <LogOut size={20} className="group-hover:scale-110 transition-transform" />
          <span className="hidden md:block text-xs uppercase tracking-wider">Salir</span>
        </button>
      </div>
    </header>
  );
}
