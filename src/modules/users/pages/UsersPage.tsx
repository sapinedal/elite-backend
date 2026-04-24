import { useState } from 'react';
import { 
  Search, 
  UserPlus, 
  Mail, 
  Building2, 
  Briefcase, 
  Edit2, 
  Key, 
  Trash2,
  Shield,
  User as UserIcon
} from 'lucide-react';
import { useUsers } from '../hooks/useUsers';
import { userService } from '../services/userService';
import { DataTable } from '../../../components/ui/DataTable';
import { UserModal } from '../components/UserModal';
import { PasswordChangeModal } from '../components/PasswordChangeModal';
import type { User } from '../types';

export default function UsersPage() {
  const { users, isLoading, refetch } = useUsers();
  const [searchTerm, setSearchTerm] = useState('');
  const [isModalOpen, setIsModalOpen] = useState(false);
  const [isPasswordModalOpen, setIsPasswordModalOpen] = useState(false);
  const [selectedUser, setSelectedUser] = useState<User | null>(null);

  const filteredUsers = users.filter(user => {
    const areaName = typeof user.area === 'object' ? user.area?.name : user.area;
    const positionName = typeof user.position === 'object' ? user.position?.name : user.position;
    
    return (
      user.name.toLowerCase().includes(searchTerm.toLowerCase()) ||
      user.email.toLowerCase().includes(searchTerm.toLowerCase()) ||
      (areaName?.toLowerCase() || '').includes(searchTerm.toLowerCase()) ||
      (positionName?.toLowerCase() || '').includes(searchTerm.toLowerCase())
    );
  });

  const handleSaveUser = async (data: any) => {
    try {
      if (selectedUser) {
        await userService.updateUser(selectedUser.id, data);
      } else {
        await userService.createUser(data);
      }
      refetch();
    } catch (error) {
      console.error(error);
      throw error;
    }
  };

  const handlePasswordChange = async (password: string) => {
    if (!selectedUser) return;
    try {
      await userService.changePassword(selectedUser.id, { 
        password, 
        password_confirmation: password 
      });
    } catch (error) {
      console.error(error);
      throw error;
    }
  };

  const handleDeleteUser = async (id: number) => {
    if (!window.confirm('¿Estás seguro de eliminar este usuario?')) return;
    try {
      await userService.deleteUser(id);
      refetch();
    } catch (error) {
      console.error(error);
    }
  };

  const columns = [
    {
      header: 'Colaborador',
      accessor: (user: User) => (
        <div className="flex items-center gap-4">
          <div className="h-10 w-10 rounded-xl bg-slate-50 flex items-center justify-center text-[#004C6C] font-black group-hover:bg-[#004C6C] group-hover:text-white transition-all duration-300">
            {user.name.charAt(0)}
          </div>
          <div className="flex flex-col">
            <span className="text-slate-800 font-black group-hover:text-[#004C6C] transition-colors">{user.name}</span>
            <span className="text-[10px] text-slate-400 font-bold uppercase tracking-widest flex items-center gap-1">
              <Mail size={10} /> {user.email}
            </span>
          </div>
        </div>
      )
    },
    {
      header: 'Área / Cargo',
      accessor: (user: User) => (
        <div className="flex flex-col gap-1">
          <span className="flex items-center gap-2 text-slate-600">
             <Building2 size={12} className="opacity-40" /> {user.area?.name || 'N/A'}
          </span>
          <span className="flex items-center gap-2 text-[11px] text-slate-400 font-bold uppercase tracking-wider">
             <Briefcase size={12} className="opacity-40" /> {user.position?.name || 'N/A'}
          </span>
        </div>
      )
    },
    {
      header: 'Roles',
      accessor: (user: User) => (
        <div className="flex flex-wrap gap-2">
          {(user.roles || ['colaborador']).map((role, idx) => (
            <span key={idx} className="px-3 py-1 bg-blue-50 text-[#004C6C] rounded-full border border-blue-100 text-[9px] font-black uppercase tracking-widest flex items-center gap-1">
              <Shield size={10} /> {role}
            </span>
          ))}
        </div>
      )
    },
    {
      header: 'Acciones',
      accessor: (user: User) => (
        <div className="flex items-center gap-2 justify-end" onClick={e => e.stopPropagation()}>
          <button 
            onClick={() => { setSelectedUser(user); setIsPasswordModalOpen(true); }}
            title="Cambiar Contraseña"
            className="p-3 text-slate-300 hover:text-[#004C6C] hover:bg-blue-50 rounded-2xl transition-all"
          >
            <Key size={18} />
          </button>
          <button 
            onClick={() => { setSelectedUser(user); setIsModalOpen(true); }}
            title="Editar Usuario"
            className="p-3 text-slate-300 hover:text-[#EE9D4C] hover:bg-orange-50 rounded-2xl transition-all"
          >
            <Edit2 size={18} />
          </button>
          <button 
            onClick={() => handleDeleteUser(user.id)}
            title="Eliminar Usuario"
            className="p-3 text-slate-300 hover:text-red-500 hover:bg-red-50 rounded-2xl transition-all"
          >
            <Trash2 size={18} />
          </button>
        </div>
      ),
      className: "text-right"
    }
  ];

  return (
    <div className="max-w-7xl mx-auto p-8 space-y-10 animate-fade-in">
      
      {/* Header */}
      <div className="flex flex-col md:flex-row md:items-end justify-between gap-6">
        <div className="space-y-1">
          <h1 className="text-4xl font-black text-[#004C6C] tracking-tight">Gestión de Usuarios</h1>
          <p className="text-slate-400 font-bold uppercase tracking-[0.2em]">
            Administra colaboradores, roles y accesos
          </p>
        </div>
        
        <button 
          onClick={() => { setSelectedUser(null); setIsModalOpen(true); }}
          className="flex items-center gap-3 px-8 py-4 bg-[#004C6C] text-white rounded-[24px] font-black text-sm uppercase tracking-widest hover:bg-[#003a53] shadow-xl shadow-blue-900/10 transition-all hover:scale-[1.02] active:scale-95 group"
        >
          <UserPlus size={20} className="transition-transform group-hover:rotate-12" />
          Nuevo Usuario
        </button>
      </div>

      {/* Filters & Stats */}
      <div className="grid grid-cols-1 lg:grid-cols-4 gap-6 items-stretch">
        <div className="lg:col-span-3 group">
          <div className="relative h-full">
            <div className="absolute left-7 top-1/2 -translate-y-1/2 text-slate-400 group-focus-within:text-[#004C6C] transition-colors z-10">
              <Search size={22} />
            </div>
            <input 
              type="text"
              placeholder="Buscar por nombre, correo o área..."
              value={searchTerm}
              onChange={(e) => setSearchTerm(e.target.value)}
              className="w-full h-full bg-white border border-slate-200 rounded-[32px] pl-16 pr-8 py-5 text-slate-700 font-bold shadow-sm group-hover:shadow-md focus:shadow-xl focus:shadow-blue-900/5 focus:border-[#004C6C] transition-all outline-none text-base placeholder:text-slate-300"
            />
          </div>
        </div>
        
        <div className="bg-[#004C6C] rounded-[32px] p-6 flex items-center justify-between shadow-xl shadow-blue-900/10 relative overflow-hidden group hover:scale-[1.02] transition-all cursor-default">
          {/* Decorative background element */}
          <div className="absolute -right-6 -bottom-6 w-32 h-32 bg-white/5 rounded-full blur-3xl group-hover:bg-white/10 transition-all duration-500" />
          
          <div className="relative z-10">
            <p className="text-[10px] font-black text-white/40 uppercase tracking-[0.2em] leading-none mb-2">Total Usuarios</p>
            <div className="flex items-baseline gap-1.5">
              <p className="text-4xl font-black text-white tracking-tighter">{users.length}</p>
              <span className="text-[10px] font-bold text-white/30 uppercase tracking-widest">Activos</span>
            </div>
          </div>
          <div className="h-16 w-16 bg-white/10 rounded-[24px] flex items-center justify-center text-white backdrop-blur-md border border-white/10 relative z-10 group-hover:rotate-6 transition-transform duration-500">
            <UserIcon size={28} />
          </div>
        </div>
      </div>

      {/* Users Table */}
      <DataTable 
        columns={columns}
        data={filteredUsers}
        isLoading={isLoading}
        onRowClick={(user) => { setSelectedUser(user); setIsModalOpen(true); }}
      />

      {/* Modals */}
      <UserModal 
        isOpen={isModalOpen}
        onClose={() => setIsModalOpen(false)}
        onSave={handleSaveUser}
        user={selectedUser}
      />

      <PasswordChangeModal 
        isOpen={isPasswordModalOpen}
        onClose={() => setIsPasswordModalOpen(false)}
        onConfirm={handlePasswordChange}
        user={selectedUser}
      />

    </div>
  );
}
