import React, { useState, useRef, useEffect } from 'react';
import { ChevronDown, Check } from 'lucide-react';

interface Option {
  value: string | number;
  label: string;
}

interface CustomSelectProps {
  options: Option[];
  value: string | number;
  onChange: (value: any) => void;
  placeholder?: string;
  label?: string;
}

export const CustomSelect: React.FC<CustomSelectProps> = ({ options, value, onChange, placeholder = 'Seleccionar...', label }) => {
  const [isOpen, setIsOpen] = useState(false);
  const containerRef = useRef<HTMLDivElement>(null);

  const selectedOption = options.find(o => o.value === value);

  useEffect(() => {
    const handleClickOutside = (event: MouseEvent) => {
      if (containerRef.current && !containerRef.current.contains(event.target as Node)) {
        setIsOpen(false);
      }
    };
    document.addEventListener('mousedown', handleClickOutside);
    return () => document.removeEventListener('mousedown', handleClickOutside);
  }, []);

  return (
    <div className="space-y-2 relative" ref={containerRef}>
      {label && <label className="text-[11px] font-bold text-slate-500 uppercase tracking-widest ml-1">{label}</label>}
      <button
        type="button"
        onClick={() => setIsOpen(!isOpen)}
        className={`w-full bg-slate-50/50 border border-slate-200 rounded-2xl px-5 py-4 text-sm font-semibold text-left flex items-center justify-between transition-all hover:bg-white hover:border-[#004C6C]/30 ${isOpen ? 'ring-4 ring-blue-50 border-[#004C6C] bg-white' : ''}`}
      >
        <span className={selectedOption ? 'text-slate-700' : 'text-slate-400 font-medium'}>
          {selectedOption ? selectedOption.label : placeholder}
        </span>
        <ChevronDown size={18} className={`text-slate-400 transition-transform duration-200 ${isOpen ? 'rotate-180' : ''}`} />
      </button>

      {isOpen && (
        <div className="absolute z-50 w-full mt-2 bg-white border border-slate-100 rounded-2xl shadow-2xl py-2 animate-in fade-in zoom-in-95 duration-200 max-h-60 overflow-y-auto">
          {options.map((option) => (
            <button
              key={option.value}
              onClick={() => {
                onChange(option.value);
                setIsOpen(false);
              }}
              className="w-full text-left px-5 py-3 text-sm font-semibold flex items-center justify-between hover:bg-slate-50 transition-colors group"
            >
              <span className={value === option.value ? 'text-[#004C6C]' : 'text-slate-600'}>
                {option.label}
              </span>
              {value === option.value && <Check size={16} className="text-[#EE9D4C]" />}
            </button>
          ))}
        </div>
      )}
    </div>
  );
};
