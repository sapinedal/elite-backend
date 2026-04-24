import React, { useState, useRef, useEffect } from 'react';
import { ChevronDown } from 'lucide-react';

interface CollapseProps {
  title: React.ReactNode;
  subtitle?: React.ReactNode;
  rightElement?: React.ReactNode;
  children: React.ReactNode;
  defaultOpen?: boolean;
  className?: string;
}

export const Collapse: React.FC<CollapseProps> = ({
  title,
  subtitle,
  rightElement,
  children,
  defaultOpen = false,
  className = ""
}) => {
  const [isOpen, setIsOpen] = useState(defaultOpen);
  const contentRef = useRef<HTMLDivElement>(null);
  const [height, setHeight] = useState<number | string>(defaultOpen ? 'auto' : 0);

  useEffect(() => {
    if (isOpen) {
      const contentHeight = contentRef.current?.scrollHeight;
      setHeight(contentHeight ? `${contentHeight}px` : 'auto');

      const timer = setTimeout(() => {
        setHeight('auto');
      }, 500);
      return () => clearTimeout(timer);
    } else {
      if (height === 'auto') {
        const contentHeight = contentRef.current?.scrollHeight;
        setHeight(`${contentHeight}px`);
        // Force reflow
        if (contentRef.current) {
          void contentRef.current.offsetHeight;
        }
      }
      setHeight(0);
    }
  }, [isOpen]);

  return (
    <div className={`group bg-white rounded-3xl border transition-all duration-500 overflow-hidden ${isOpen ? 'border-[#004C6C]/40 shadow-xl shadow-[#004C6C]/5 ring-1 ring-[#004C6C]/5' : 'border-slate-200 shadow-sm hover:border-slate-300'} ${className}`}>
      <div
        className={`p-6 flex items-center justify-between cursor-pointer select-none transition-colors duration-300 ${isOpen ? 'bg-slate-50/30' : 'bg-white'}`}
        onClick={() => setIsOpen(!isOpen)}
      >
        <div className="flex-1">
          <div className="flex flex-col sm:flex-row sm:items-center gap-1 sm:gap-4">
            <div className={`text-[15px] font-black transition-colors duration-300 ${isOpen ? 'text-[#004C6C]' : 'text-slate-700 group-hover:text-[#004C6C]'}`}>{title}</div>
            {subtitle && <div className="text-[10px] text-slate-400 font-black uppercase tracking-widest">{subtitle}</div>}
          </div>
        </div>

        <div className="flex items-center gap-6">
          {rightElement}
          <div className={`w-9 h-9 rounded-2xl flex items-center justify-center transition-all duration-500 ${isOpen ? 'bg-[#004C6C] text-white rotate-180 shadow-lg shadow-[#004C6C]/20' : 'bg-slate-50 text-slate-400 group-hover:bg-slate-100 group-hover:text-slate-500'}`}>
            <ChevronDown size={18} />
          </div>
        </div>
      </div>

      <div
        className="overflow-hidden transition-all duration-500 ease-in-out"
        style={{ height }}
      >
        <div ref={contentRef} className="p-8 pt-2 border-t border-slate-100/60">
          <div className={`transition-all duration-700 delay-100 ${isOpen ? 'opacity-100 translate-y-0' : 'opacity-0 -translate-y-4'}`}>
            {children}
          </div>
        </div>
      </div>
    </div>
  );
};
