import React from 'react';
import PageHeader from '@/Components/shared/PageHeader';
import DataTable from '@/Components/shared/DataTable';
import { BarChart3, ShoppingCart, DollarSign, TrendingUp } from 'lucide-react';
import { formatCurrency } from '@/data/mockData';
import MainLayout from '@/Components/layout/MainLayout';
import { Head, router } from '@inertiajs/react';

interface Customer {
    id: string;
    name: string;
}

interface User {
    id: number;
    name: string;
}

interface SaleItem {
    quantity: number;
}

interface Sale {
    id: string;
    sale_number: number;
    created_at: string;
    customer_id?: string;
    user_id: number;
    total: number;
    items: SaleItem[];
    customer?: Customer;
    user?: User;
}

interface DailySale {
    date: string;
    value: number;
}

interface Stats {
    totalVendas: number;
    valorTotal: number;
    ticketMedio: number;
    itensVendidos: number;
}

interface Props {
    sales: Sale[];
    stats: Stats;
    dailySales: DailySale[];
    period: string;
}

const SalesReportPage: React.FC<Props> = ({ sales, stats, dailySales, period }) => {
    const handlePeriodChange = (newPeriod: string) => {
        router.get('/relatorios/vendas', { period: newPeriod }, { preserveState: true });
    };

    const columns = [
        { key: 'date', header: 'Data', render: (_: unknown, s: Sale) => new Date(s.created_at).toLocaleDateString('pt-BR') },
        { key: 'id', header: 'Nº Venda', render: (_: unknown, s: Sale) => `#${String(s.sale_number).padStart(6, '0')}` },
        { key: 'customer', header: 'Cliente', render: (_: unknown, s: Sale) => s.customer?.name || 'Consumidor' },
        { key: 'user', header: 'Vendedor', render: (_: unknown, s: Sale) => s.user?.name || '-' },
        { key: 'items', header: 'Itens', render: (_: unknown, s: Sale) => s.items.reduce((sum, i) => sum + i.quantity, 0) },
        { key: 'total', header: 'Total', render: (_: unknown, s: Sale) => formatCurrency(s.total) },
    ];

    const maxValue = Math.max(...dailySales.map(d => d.value), 1);

    // Calculate summary stats from filtered sales
    const maiorVenda = sales.length > 0 ? Math.max(...sales.map(s => s.total)) : 0;
    const menorVenda = sales.length > 0 ? Math.min(...sales.map(s => s.total)) : 0;
    const mediaDia = dailySales.length > 0 ? stats.valorTotal / dailySales.length : 0;

    return (
        <MainLayout>
            <Head title="Relatório de Vendas" />
            <div>
                <PageHeader title="Relatório de Vendas" subtitle="Análise de vendas por período" icon={<BarChart3 className="h-6 w-6" />} />

                <div className="mb-4">
                    <select value={period} onChange={e => handlePeriodChange(e.target.value)} className="w-[200px] h-10 px-3 py-2 rounded-md border border-input bg-background text-sm ring-offset-background focus:outline-none focus:ring-2 focus:ring-ring focus:ring-offset-2">
                        <option value="all">Todo período</option>
                        <option value="today">Hoje</option>
                        <option value="week">Última semana</option>
                        <option value="month">Este mês</option>
                    </select>
                </div>

                <div className="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
                    <div className="bg-card border border-border rounded-lg p-4 flex items-center gap-3">
                        <div className="p-2 bg-primary/10 rounded-lg"><ShoppingCart className="h-5 w-5 text-primary" /></div>
                        <div><p className="text-sm text-muted-foreground">Total de Vendas</p><p className="text-xl font-bold">{stats.totalVendas}</p></div>
                    </div>
                    <div className="bg-card border border-border rounded-lg p-4 flex items-center gap-3">
                        <div className="p-2 bg-primary/10 rounded-lg"><DollarSign className="h-5 w-5 text-primary" /></div>
                        <div><p className="text-sm text-muted-foreground">Valor Total</p><p className="text-xl font-bold">{formatCurrency(stats.valorTotal)}</p></div>
                    </div>
                    <div className="bg-card border border-border rounded-lg p-4 flex items-center gap-3">
                        <div className="p-2 bg-primary/10 rounded-lg"><TrendingUp className="h-5 w-5 text-primary" /></div>
                        <div><p className="text-sm text-muted-foreground">Ticket Médio</p><p className="text-xl font-bold">{formatCurrency(stats.ticketMedio)}</p></div>
                    </div>
                    <div className="bg-card border border-border rounded-lg p-4 flex items-center gap-3">
                        <div className="p-2 bg-primary/10 rounded-lg"><BarChart3 className="h-5 w-5 text-primary" /></div>
                        <div><p className="text-sm text-muted-foreground">Itens Vendidos</p><p className="text-xl font-bold">{stats.itensVendidos}</p></div>
                    </div>
                </div>

                <div className="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-6">
                    <div className="lg:col-span-2 bg-card border border-border rounded-lg p-6">
                        <h3 className="text-lg font-semibold mb-4">Vendas por Dia</h3>
                        <div className="flex items-end gap-2 h-40">
                            {dailySales.map((d, i) => (
                                <div key={i} className="flex-1 flex flex-col items-center">
                                    <div className="w-full bg-primary rounded-t" style={{ height: `${(d.value / maxValue) * 100}%`, minHeight: 4 }} />
                                    <span className="text-xs mt-1 text-muted-foreground">{new Date(d.date).toLocaleDateString('pt-BR').slice(0, 5)}</span>
                                </div>
                            ))}
                        </div>
                    </div>
                    <div className="bg-card border border-border rounded-lg p-6">
                        <h3 className="text-lg font-semibold mb-4">Resumo</h3>
                        <div className="space-y-3">
                            <div className="flex justify-between"><span className="text-muted-foreground">Maior venda</span><span className="font-medium">{formatCurrency(maiorVenda)}</span></div>
                            <div className="flex justify-between"><span className="text-muted-foreground">Menor venda</span><span className="font-medium">{formatCurrency(menorVenda)}</span></div>
                            <div className="flex justify-between"><span className="text-muted-foreground">Média/dia</span><span className="font-medium">{formatCurrency(mediaDia)}</span></div>
                        </div>
                    </div>
                </div>

                <DataTable columns={columns} data={sales} keyField="id" />
            </div>
        </MainLayout>
    );
};

export default SalesReportPage;
