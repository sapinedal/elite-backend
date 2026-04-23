import { useState } from 'react';
import { useAuth } from '../../../context/AuthContext';
import { useNavigate } from 'react-router-dom';
import { authService } from '../services/authService';
import { useNotification } from '../../../context/NotificationContext';
import { Mail, Lock, LogIn, Sparkles } from 'lucide-react';
import logoinver from '../../../assets/logo_inver.svg';
import logoelite from '../../../assets/logo_inver_bg.png';

export default function LoginPage() {
  const { login } = useAuth();
  const { showNotification } = useNotification();
  const [email, setEmail] = useState('');
  const [password, setPassword] = useState('');
  const [isLoading, setIsLoading] = useState(false);
  const navigate = useNavigate();

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    setIsLoading(true);
    try {
      const data = await authService.login({ email, password });
      await login({ token: data.token });
      showNotification(`Bienvenido de nuevo, ${data.user.name}`, 'success');
      navigate('/app/dashboard');
    } catch (error: any) {
      console.error(error);
      const message = error.response?.data?.message || 'Error al iniciar sesión. Verifica tus credenciales.';
      showNotification(message, 'error');
    } finally {
      setIsLoading(false);
    }
  };

  return (
    <div className="min-h-screen flex items-center justify-center bg-[#f8fafc] relative overflow-hidden p-6">
      {/* Decorative Elements */}
      <div className="absolute top-[-10%] left-[-10%] w-[40%] h-[40%] bg-[#004C6C]/5 rounded-full blur-[120px]" />
      <div className="absolute bottom-[-10%] right-[-10%] w-[40%] h-[40%] bg-[#EE9D4C]/5 rounded-full blur-[120px]" />

      <div className="w-full max-w-[1100px] bg-white rounded-[40px] shadow-[0_32px_80px_rgba(0,0,0,0.06)] border border-slate-100 overflow-hidden flex flex-col md:flex-row relative z-10 min-h-[650px]">

        {/* Left Side: Branding/Visual */}
        <div className="w-full md:w-1/2 bg-[#004C6C] p-12 md:p-20 flex flex-col justify-between relative overflow-hidden">
          <div className="absolute top-0 left-0 w-full h-full opacity-10 pointer-events-none">
            <div className="absolute top-10 left-10 w-40 h-40 border-4 border-white rounded-full" />
            <div className="absolute bottom-20 right-10 w-60 h-60 border-2 border-white rounded-full" />
            <div className="absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 w-[800px] h-[800px] border border-white/20 rounded-full" />
          </div>

          <div className="relative z-10">

            <div className="mb-12 flex items-center justify-center">
              <img src={logoinver} alt="Elite Logo" className="h-20 w-auto brightness-0 invert" />
              <img src={logoelite} alt="logoelite" className="h-20 w-auto ml-5" />
            </div>
            <h2 className="text-4xl md:text-5xl font-black text-white leading-[1.1] tracking-tight">
              Potencia el <span className="text-[#EE9D4C]">Talento</span> de tu equipo.
            </h2>
            <p className="text-blue-100/60 mt-6 text-lg font-medium leading-relaxed max-w-sm">
              La plataforma inteligente para la gestión de desempeño y KPIs de alto impacto.
            </p>
          </div>

          <div className="relative z-10 flex items-center gap-6 text-white/40">
            <div className="flex flex-col">
              <span className="text-2xl font-black text-white">100%</span>
              <span className="text-[10px] font-bold uppercase tracking-widest">Seguro</span>
            </div>
            <div className="h-8 w-px bg-white/10" />
            <div className="flex flex-col">
              <span className="text-2xl font-black text-white">IA</span>
              <span className="text-[10px] font-bold uppercase tracking-widest">Potenciado</span>
            </div>
          </div>
        </div>

        {/* Right Side: Login Form */}
        <div className="w-full md:w-1/2 p-12 md:p-20 flex flex-col justify-center bg-white">
          <div className="max-w-sm mx-auto w-full space-y-10">
            <div>
              <h3 className="text-3xl font-black text-[#004C6C] tracking-tight">Bienvenido</h3>
              <p className="text-slate-400 font-bold uppercase tracking-widest text-[10px] mt-2">Ingresa tus credenciales para acceder</p>
            </div>

            <form onSubmit={handleSubmit} className="space-y-6">
              <div className="space-y-4">
                <div className="group space-y-2">
                  <label className="text-[11px] font-black text-slate-400 uppercase tracking-widest ml-1 transition-colors group-focus-within:text-[#004C6C]">Email Corporativo</label>
                  <div className="relative">
                    <div className="absolute left-5 top-1/2 -translate-y-1/2 text-slate-300 group-focus-within:text-[#004C6C] transition-colors">
                      <Mail size={18} />
                    </div>
                    <input
                      type="email"
                      required
                      placeholder="ejemplo@elite.com"
                      value={email}
                      onChange={(e) => setEmail(e.target.value)}
                      className="w-full bg-slate-50 border border-slate-100 rounded-2xl pl-14 pr-6 py-4 text-sm font-bold text-slate-700 outline-none focus:bg-white focus:border-[#004C6C] focus:ring-4 focus:ring-blue-50 transition-all placeholder:text-slate-200"
                    />
                  </div>
                </div>

                <div className="group space-y-2">
                  <label className="text-[11px] font-black text-slate-400 uppercase tracking-widest ml-1 transition-colors group-focus-within:text-[#EE9D4C]">Contraseña</label>
                  <div className="relative">
                    <div className="absolute left-5 top-1/2 -translate-y-1/2 text-slate-300 group-focus-within:text-[#EE9D4C] transition-colors">
                      <Lock size={18} />
                    </div>
                    <input
                      type="password"
                      required
                      placeholder="••••••••"
                      value={password}
                      onChange={(e) => setPassword(e.target.value)}
                      className="w-full bg-slate-50 border border-slate-100 rounded-2xl pl-14 pr-6 py-4 text-sm font-bold text-slate-700 outline-none focus:bg-white focus:border-[#EE9D4C] focus:ring-4 focus:ring-orange-50 transition-all placeholder:text-slate-200"
                    />
                  </div>
                </div>
              </div>

              <div className="flex items-center justify-between px-1">
                <label className="flex items-center gap-2 cursor-pointer group">
                  <input type="checkbox" className="w-4 h-4 rounded border-slate-200 text-[#004C6C] focus:ring-[#004C6C]" />
                  <span className="text-xs font-bold text-slate-400 group-hover:text-slate-600 transition-colors">Recordarme</span>
                </label>
                <button type="button" className="text-xs font-bold text-[#004C6C] hover:underline">¿Olvidaste tu contraseña?</button>
              </div>

              <button
                type="submit"
                disabled={isLoading}
                className="w-full bg-[#004C6C] text-white rounded-2xl py-4 font-black text-sm uppercase tracking-[0.2em] shadow-xl shadow-blue-900/10 hover:bg-[#003a53] hover:shadow-blue-900/20 transition-all flex items-center justify-center gap-3 group disabled:opacity-50 active:scale-95"
              >
                {isLoading ? (
                  <div className="h-5 w-5 border-2 border-white/30 border-t-white rounded-full animate-spin" />
                ) : (
                  <>
                    Ingresar
                    <LogIn size={18} className="transition-transform group-hover:translate-x-1" />
                  </>
                )}
              </button>
            </form>

            <div className="pt-6 border-t border-slate-50 flex items-center justify-center gap-2">
              <Sparkles size={14} className="text-[#EE9D4C]" />
              <p className="text-[10px] font-bold text-slate-300 uppercase tracking-widest">Plataforma de Alto Desempeño</p>
            </div>
          </div>
        </div>
      </div>
    </div>
  );
}
