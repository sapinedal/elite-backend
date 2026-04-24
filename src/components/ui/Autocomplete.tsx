import React, { useState, useRef, useEffect } from 'react';
import { Search, Check, X } from 'lucide-react';

interface Option {
  value: string | number;
  label: string;
  sublabel?: string;
}

interface AutocompleteProps {
  options: Option[];
  value: string | number | null;
  onChange: (value: any) => void;
  placeholder?: string;
  label?: string;
}

export const Autocomplete: React.FC<AutocompleteProps> = ({ options, value, onChange, placeholder = 'Buscar...', label }) => {
  const [isOpen, setIsOpen] = useState(false);
  const [query, setQuery] = useState('');
  const containerRef = useRef<HTMLDivElement>(null);

  const selectedOption = options.find(o => o.value === value);

  useEffect(() => {
    if (isOpen && !query && selectedOption) {
      // No reseteamos el query inmediatamente para que el usuario vea lo que seleccionó?
      // Opcional
    }
  }, [isOpen]);

  const filteredOptions = query === ''
    ? options
    : options.filter((option) =>
        option.label.toLowerCase().includes(query.toLowerCase()) ||
        (option.sublabel && option.sublabel.toLowerCase().includes(query.toLowerCase()))
      );

  useEffect(() => {
    const handleClickOutside = (event: MouseEvent) => {
      if (containerRef.current && !containerRef.current.contains(event.target as Node)) {
        setIsOpen(false);
        setQuery('');
      }
    };
    document.addEventListener('mousedown', handleClickOutside);
    return () => document.removeEventListener('mousedown', handleClickOutside);
  }, []);

  return (
    <div className="space-y-2 relative" ref={containerRef}>
      {label && <label className="text-[11px] font-bold text-slate-500 uppercase tracking-widest ml-1">{label}</label>}
      
      <div className={`relative group transition-all`}>
        <div className={`absolute left-5 top-1/2 -translate-y-1/2 text-slate-400 group-focus-within:text-[#004C6C] transition-colors`}>
          <Search size={18} />
        </div>
        <input
          type="text"
          value={isOpen ? query : (selectedOption?.label || '')}
          onChange={(e) => {
            setQuery(e.target.value);
            setIsOpen(true);
          }}
          onFocus={() => setIsOpen(true)}
          placeholder={selectedOption ? selectedOption.label : placeholder}
          className={`w-full bg-slate-50/50 border border-slate-200 rounded-2xl pl-12 pr-12 py-4 text-sm font-semibold text-slate-700 focus:ring-4 focus:ring-blue-50 focus:border-[#004C6C] outline-none transition-all placeholder:text-slate-400 ${isOpen ? 'bg-white border-[#004C6C]' : ''}`}
        />
        { (query || selectedOption) && (
            <button 
                onClick={() => {
                    onChange(null);
                    setQuery('');
                    setIsOpen(false);
                }}
                className="absolute right-5 top-1/2 -translate-y-1/2 text-slate-300 hover:text-slate-500 transition-colors"
            >
                <X size={16} />
            </button>
        )}
      </div>

      {isOpen && (
        <div className="absolute z-50 w-full mt-2 bg-white border border-slate-100 rounded-2xl shadow-2xl py-2 animate-in fade-in zoom-in-95 duration-200 max-h-72 overflow-y-auto">
          {filteredOptions.length === 0 ? (
            <div className="px-5 py-8 text-center space-y-2 text-slate-400">
               <p className="text-sm font-medium">No se encontraron resultados</p>
               <p className="text-[10px] uppercase font-bold tracking-widest opacity-50">Intenta con otra búsqueda</p>
            </div>
          ) : (
            filteredOptions.map((option) => (
              <button
                key={option.value}
                onClick={() => {
                  onChange(option.value);
                  setQuery('');
                  setIsOpen(false);
                }}
                className="w-full text-left px-5 py-4 text-sm font-semibold flex items-center justify-between hover:bg-slate-50 transition-colors group border-b border-slate-50 last:border-0"
              >
                <div className="flex flex-col">
                  <span className={value === option.value ? 'text-[#004C6C]' : 'text-slate-700'}>
                    {option.label}
                  </span>
                  {option.sublabel && (
                    <span className="text-[10px] text-slate-400 font-bold uppercase tracking-wider">{option.sublabel}</span>
                  )}
                </div>
                {value === option.value && <Check size={18} className="text-[#EE9D4C]" />}
              </button>
            ))
          )}
        </div>
      )}
    </div>
  );
};
