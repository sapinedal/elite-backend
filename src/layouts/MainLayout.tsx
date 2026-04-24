import { useState } from 'react';
import { Outlet } from 'react-router-dom';
import Header from './Header';
import Sidebar from './Sidebar';

export default function MainLayout() {
  const [isSidebarOpen, setIsSidebarOpen] = useState(false);
  const [isExpanded, setIsExpanded] = useState(true);

  return (
    <div id="root-layout" className="flex h-screen bg-slate-50 relative overflow-hidden transition-all duration-500">
      {/* Sidebar Section */}
      <Sidebar 
        isExpanded={isExpanded}
        onToggle={() => setIsExpanded(!isExpanded)}
        isMobileOpen={isSidebarOpen}
        onMobileClose={() => setIsSidebarOpen(false)}
      />

      <div className="flex-1 flex flex-col min-w-0 transition-all duration-300">
        <Header onMenuClick={() => setIsSidebarOpen(true)} />
        <main className="flex-1 overflow-y-auto no-scrollbar scroll-smooth animate-fade-in">
          <div className="p-4 md:p-10 animate-slide-up">
            <Outlet />
          </div>
        </main>
      </div>
    </div>
  );
}

