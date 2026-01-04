import React from 'react';

interface StatusBadgeProps {
  status: 'active' | 'inactive' | 'pending' | 'paid' | 'overdue' | 'completed' | 'cancelled' | 'low' | 'critical';
  label?: string;
}

const StatusBadge: React.FC<StatusBadgeProps> = ({ status, label }) => {
  const config: Record<string, { className: string; defaultLabel: string }> = {
    active: { className: 'erp-badge-success', defaultLabel: 'Ativo' },
    inactive: { className: 'erp-badge-muted', defaultLabel: 'Inativo' },
    pending: { className: 'erp-badge-warning', defaultLabel: 'Pendente' },
    paid: { className: 'erp-badge-success', defaultLabel: 'Pago' },
    overdue: { className: 'erp-badge-destructive', defaultLabel: 'Vencido' },
    completed: { className: 'erp-badge-success', defaultLabel: 'Concluída' },
    cancelled: { className: 'erp-badge-destructive', defaultLabel: 'Cancelada' },
    low: { className: 'erp-badge-warning', defaultLabel: 'Baixo' },
    critical: { className: 'erp-badge-destructive', defaultLabel: 'Crítico' },
  };

  const { className, defaultLabel } = config[status] || config.pending;

  return <span className={className}>{label || defaultLabel}</span>;
};

export default StatusBadge;
