import React from 'react';
import { Plus, Trash2, Table, X } from 'lucide-react';
import { Modal } from '../../../components/ui/Modal';

interface KPIDetailTableProps {
  data: {
    headers: string[];
    rows: string[][];
  } | null;
  onChange: (newData: { headers: string[]; rows: string[][] } | null) => void;
}

export const KPIDetailTable: React.FC<KPIDetailTableProps> = ({ data, onChange }) => {
  const [isRemoveModalOpen, setIsRemoveModalOpen] = React.useState(false);
  const isEnabled = !!data && Array.isArray(data.headers) && Array.isArray(data.rows);

  const handleInitialize = () => {
    onChange({
      headers: ['Descripción', 'Valor', 'Observación'],
      rows: [['', '', '']]
    });
  };

  const handleRemove = () => {
    setIsRemoveModalOpen(true);
  };

  const confirmRemove = () => {
    onChange(null);
  };

  const handleUpdateHeader = (colIdx: number, value: string) => {
    if (!data) return;
    const newHeaders = [...data.headers];
    newHeaders[colIdx] = value;
    onChange({ ...data, headers: newHeaders });
  };

  const handleUpdateCell = (rowIdx: number, colIdx: number, value: string) => {
    if (!data) return;
    const newRows = data.rows.map((row, rIdx) => 
      rIdx === rowIdx ? row.map((cell, cIdx) => cIdx === colIdx ? value : cell) : row
    );
    onChange({ ...data, rows: newRows });
  };

  const handleAddColumn = () => {
    if (!data) return;
    const newHeaders = [...data.headers, `Columna ${data.headers.length + 1}`];
    const newRows = data.rows.map(row => [...row, '']);
    onChange({ headers: newHeaders, rows: newRows });
  };

  const handleDeleteColumn = (colIdx: number) => {
    if (!data || data.headers.length <= 1) return;
    const newHeaders = data.headers.filter((_, i) => i !== colIdx);
    const newRows = data.rows.map(row => row.filter((_, i) => i !== colIdx));
    onChange({ headers: newHeaders, rows: newRows });
  };

  const handleAddRow = () => {
    if (!data) return;
    const newRow = new Array(data.headers.length).fill('');
    onChange({ ...data, rows: [...data.rows, newRow] });
  };

  const handleDeleteRow = (rowIdx: number) => {
    if (!data || data.rows.length <= 1) {
        // Si es la última fila, solo la limpiamos o permitimos borrarla si se queda vacía? 
        // El requerimiento dice eliminar filas. Si borra todas las filas, tal vez dejar una vacía es mejor.
        if (data && data.rows.length === 1) {
            const clearedRow = new Array(data.headers.length).fill('');
            onChange({ ...data, rows: [clearedRow] });
            return;
        }
    }
    if (!data) return;
    const newRows = data.rows.filter((_, i) => i !== rowIdx);
    onChange({ ...data, rows: newRows });
  };

  if (!isEnabled) {
    return (
      <button
        onClick={handleInitialize}
        className="flex items-center gap-2.5 text-xs font-extrabold text-slate-400 hover:text-[#004C6C] transition-all py-2 px-1 group"
      >
        <div className="p-1.5 bg-slate-50 group-hover:bg-blue-50 rounded-lg transition-colors">
          <Table size={14} className="group-hover:scale-110 transition-transform" />
        </div>
        <span className="tracking-widest uppercase">Agregar tabla de detalle</span>
      </button>
    );
  }

  return (
    <div className="mt-2 border border-slate-100 rounded-[24px] overflow-hidden bg-slate-50/30 animate-in fade-in slide-in-from-top-2 duration-300">
      <div className="overflow-x-auto">
        <table className="w-full text-left border-collapse">
          <thead>
            <tr className="bg-slate-100/50">
              {data.headers.map((header, colIdx) => (
                <th key={colIdx} className="p-4 border-b border-r border-slate-100 min-w-[150px]">
                  <div className="flex items-center gap-2 group/col">
                    <input
                      type="text"
                      value={header}
                      onChange={(e) => handleUpdateHeader(colIdx, e.target.value)}
                      className="bg-transparent border-none focus:ring-2 focus:ring-blue-100 rounded-lg px-2 py-1 text-[11px] font-black uppercase tracking-wider text-[#004C6C] w-full outline-none transition-all"
                    />
                    <button
                      onClick={() => handleDeleteColumn(colIdx)}
                      className="opacity-0 group-hover/col:opacity-100 text-slate-300 hover:text-red-500 transition-all p-1"
                      title="Eliminar columna"
                    >
                      <Trash2 size={12} />
                    </button>
                  </div>
                </th>
              ))}
              <th className="p-4 border-b border-slate-100 w-12 text-center">
                <button
                  onClick={handleAddColumn}
                  className="p-2 hover:bg-white rounded-xl text-[#EE9D4C] shadow-sm transition-all hover:scale-110"
                  title="Agregar columna"
                >
                  <Plus size={16} />
                </button>
              </th>
            </tr>
          </thead>
          <tbody>
            {data.rows.map((row, rowIdx) => (
              <tr key={rowIdx} className="hover:bg-white/80 group/row transition-colors">
                {row.map((cell, colIdx) => (
                  <td key={colIdx} className="p-0 border-b border-r border-slate-100">
                    <input
                      type="text"
                      value={cell}
                      onChange={(e) => handleUpdateCell(rowIdx, colIdx, e.target.value)}
                      className="w-full p-4 text-sm font-medium text-slate-600 bg-transparent outline-none focus:bg-white focus:ring-inset focus:ring-1 focus:ring-blue-50 transition-all"
                    />
                  </td>
                ))}
                <td className="p-2 border-b border-slate-100 text-center">
                  <button
                    onClick={() => handleDeleteRow(rowIdx)}
                    className="opacity-0 group-hover/row:opacity-100 p-1.5 text-slate-200 hover:text-red-400 transition-all hover:bg-red-50 rounded-lg"
                    title="Eliminar fila"
                  >
                    <Trash2 size={16} />
                  </button>
                </td>
              </tr>
            ))}
          </tbody>
        </table>
      </div>

      <div className="p-4 flex items-center justify-between bg-white/40 border-t border-slate-100">
        <button
          onClick={handleAddRow}
          className="flex items-center gap-2 text-[11px] font-black text-[#EE9D4C] hover:bg-[#EE9D4C] hover:text-white px-5 py-2.5 rounded-xl border-2 border-[#EE9D4C]/20 hover:border-[#EE9D4C] transition-all tracking-widest uppercase shadow-sm"
        >
          <Plus size={14} /> Agregar fila
        </button>

        <button
          onClick={handleRemove}
          className="flex items-center gap-2 text-[11px] font-black text-red-400 hover:text-red-600 hover:bg-red-50 px-5 py-2.5 rounded-xl transition-all tracking-widest uppercase"
        >
          <X size={14} /> Quitar tabla
        </button>
      </div>

      <Modal
        isOpen={isRemoveModalOpen}
        onClose={() => setIsRemoveModalOpen(false)}
        onConfirm={confirmRemove}
        title="¿Quitar tabla de detalle?"
        message="Se perderán permanentemente todos los datos y el desglose capturado en esta tabla para este indicador."
        confirmText="Sí, quitar tabla"
        cancelText="Mantener tabla"
        type="danger"
      />
    </div>
  );
};

