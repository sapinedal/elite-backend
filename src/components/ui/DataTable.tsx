import React from 'react';

interface Column<T> {
  header: string;
  accessor: keyof T | ((item: T) => React.ReactNode);
  className?: string;
}

interface DataTableProps<T> {
  columns: Column<T>[];
  data: T[];
  onRowClick?: (item: T) => void;
  isLoading?: boolean;
  emptyMessage?: string;
}

export function DataTable<T extends { id: string | number }>({
  columns,
  data,
  onRowClick,
  isLoading,
  emptyMessage = "No se encontraron resultados"
}: DataTableProps<T>) {
  return (
    <div className="w-full bg-white rounded-[32px] border border-slate-100 shadow-sm overflow-hidden animate-fade-in">
      <div className="overflow-x-auto">
        <table className="w-full border-collapse text-left">
          <thead>
            <tr className="bg-slate-50/50 border-b border-slate-100">
              {columns.map((column, index) => (
                <th
                  key={index}
                  className={`px-8 py-6 text-[10px] font-black text-slate-400 uppercase tracking-[0.2em] ${column.className || ''}`}
                >
                  {column.header}
                </th>
              ))}
            </tr>
          </thead>
          <tbody className="divide-y divide-slate-50">
            {isLoading ? (
              [1, 2, 3, 4, 5].map((i) => (
                <tr key={i} className="animate-pulse">
                  {columns.map((_, j) => (
                    <td key={j} className="px-8 py-6">
                      <div className="h-4 bg-slate-100 rounded-full w-24"></div>
                    </td>
                  ))}
                </tr>
              ))
            ) : data.length > 0 ? (
              data.map((item, index) => (
                <tr
                  key={item.id}
                  onClick={() => onRowClick?.(item)}
                  className={`
                    group transition-all duration-300
                    ${onRowClick ? 'cursor-pointer hover:bg-[#004C6C]/2' : ''}
                    animate-slide-up
                  `}
                  style={{ animationDelay: `${index * 30}ms` }}
                >
                  {columns.map((column, j) => (
                    <td
                      key={j}
                      className={`px-8 py-6 text-sm font-bold text-slate-600 group-hover:text-[#004C6C] transition-colors ${column.className || ''}`}
                    >
                      {typeof column.accessor === 'function'
                        ? column.accessor(item)
                        : (item[column.accessor] as React.ReactNode)}
                    </td>
                  ))}
                </tr>
              ))
            ) : (
              <tr>
                <td colSpan={columns.length} className="px-8 py-20 text-center">
                  <p className="text-slate-400 font-bold italic">{emptyMessage}</p>
                </td>
              </tr>
            )}
          </tbody>
        </table>
      </div>
    </div>
  );
}
